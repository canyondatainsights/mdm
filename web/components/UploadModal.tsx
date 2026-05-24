"use client";

import { api } from "@/lib/api";
import type { Dimensions } from "@/lib/types";
import { useEffect, useRef, useState } from "react";
import { Modal } from "./Modal";

const labelStyle = { fontSize: 12, fontWeight: 600, color: "var(--fg-2)", display: "block", marginBottom: 5 } as const;
const fieldStyle = {
  width: "100%", padding: "8px 10px", borderRadius: 8, border: "1px solid var(--border)",
  background: "var(--panel)", fontSize: 12.5, color: "var(--fg)", fontFamily: "inherit",
} as const;
const miniField = { ...fieldStyle, padding: "5px 7px", fontSize: 12, borderRadius: 6 } as const;

const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ");

type Conf = "high" | "medium" | "low";
type ReviewRow = {
  filename: string;
  isUrl: boolean;
  mdm_vendor: string;
  data_platform: string;
  product: string;
  product_version: string;
  domain: string;
  extension: string;
  financial_model: string;
  scope: string;
  confidence: Conf;
  reasoning: string | null;
  proposed: { value: string; label: string } | null;
  applyNew: boolean;
};

const CONF_COLOR: Record<Conf, string> = { high: "var(--ok)", medium: "var(--accent)", low: "var(--danger)" };

export function UploadModal({ onClose, onUploaded }: { onClose: () => void; onUploaded?: () => void }) {
  const [dims, setDims] = useState<Dimensions | null>(null);
  const [vendor, setVendor] = useState("");
  const [platform, setPlatform] = useState("");
  const [product, setProduct] = useState("");
  const [version, setVersion] = useState("");
  const [domain, setDomain] = useState("");
  const [scope, setScope] = useState("vendor-specific");
  const [url, setUrl] = useState("");
  const [crawlReq, setCrawlReq] = useState<"idle" | "sending" | "done">("idle");
  const [busy, setBusy] = useState(false);
  const [classifying, setClassifying] = useState(false);
  const [reviews, setReviews] = useState<ReviewRow[] | null>(null);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [tracked, setTracked] = useState<{ path: string; label: string }[]>([]);
  const [statuses, setStatuses] = useState<Record<string, { status: string; chunks: number; needs_metadata: boolean }>>({});
  const fileRef = useRef<HTMLInputElement>(null);

  useEffect(() => { api.dimensions().then(setDims).catch(() => {}); }, []);

  // Poll ingestion status of the just-uploaded sources until each is ready/failed.
  useEffect(() => {
    if (tracked.length === 0) return;
    let active = true;
    const poll = async () => {
      try {
        const r = await api.uploadStatus(tracked.map((t) => t.path));
        if (!active) return;
        setStatuses(r.statuses);
        if (tracked.every((t) => ["ready", "failed"].includes(r.statuses[t.path]?.status))) {
          clearInterval(id);
          onUploaded?.();
        }
      } catch { /* keep polling */ }
    };
    const id = setInterval(poll, 2000);
    poll();
    return () => { active = false; clearInterval(id); };
  }, [tracked, onUploaded]);

  const productSuggestions = (dims?.products?.[vendor] ?? []);
  const updateRow = (i: number, patch: Partial<ReviewRow>) =>
    setReviews((rs) => (rs ? rs.map((r, j) => (j === i ? { ...r, ...patch } : r)) : rs));

  // Scan & classify the selected files and/or URL (optional) — pre-fills per-source tags for review.
  const scanAndClassify = async () => {
    const files = fileRef.current?.files;
    const hasFiles = files && files.length > 0;
    if (!hasFiles && !url.trim()) { setMsg({ ok: false, text: "Add files or a URL to scan." }); return; }
    setClassifying(true); setMsg(null);
    try {
      const form = new FormData();
      if (hasFiles) Array.from(files).forEach((f) => form.append("files[]", f));
      if (url.trim()) form.append("url", url.trim());
      const r = await api.classifyUploads(form);
      setReviews(r.files.map((row) => {
        const s = row.suggestion;
        return {
          filename: row.filename,
          isUrl: !!row.is_url,
          mdm_vendor: s.mdm_vendor ?? "",
          data_platform: s.data_platform ?? "",
          product: s.product ?? "",
          product_version: "",
          domain: s.domain ?? "",
          extension: s.extension ?? "",
          financial_model: s.financial_model ?? "",
          scope: s.mdm_vendor || s.data_platform ? "vendor-specific" : "neutral",
          confidence: s.confidence,
          reasoning: s.error ? `Classify failed: ${s.error}` : s.reasoning,
          proposed: s.proposed_subject,
          applyNew: false,
        };
      }));
    } catch (e) {
      setMsg({ ok: false, text: (e as Error).message });
    } finally {
      setClassifying(false);
    }
  };

  const requestSiteCrawl = async () => {
    if (!url.trim() || crawlReq === "sending") return;
    setCrawlReq("sending");
    try { await api.requestCrawl(url.trim()); setCrawlReq("done"); }
    catch (e) { setCrawlReq("idle"); setMsg({ ok: false, text: (e as Error).message }); }
  };

  const submit = async () => {
    const files = fileRef.current?.files;
    if ((!files || files.length === 0) && !url.trim()) {
      setMsg({ ok: false, text: "Add at least one file or a reference URL." });
      return;
    }
    setBusy(true); setMsg(null);
    try {
      const form = new FormData();
      if (files) Array.from(files).forEach((f) => form.append("files[]", f));
      if (url.trim()) form.append("url", url.trim());
      // Global defaults — applied to the URL and any file without a per-file override.
      if (vendor) form.append("mdm_vendor", vendor);
      if (platform) form.append("data_platform", platform);
      if (product.trim()) form.append("product", product.trim());
      if (version.trim()) form.append("product_version", version.trim());
      if (domain) form.append("domain", domain);
      if (scope) form.append("scope", scope);

      // Per-source tags from the review step (override globals; approve proposed new subjects).
      // File rows go in `meta` (keyed by filename); the URL row goes in `url_meta`.
      if (reviews && reviews.length) {
        const meta: Record<string, Record<string, unknown>> = {};
        for (const r of reviews) {
          const tags: Record<string, unknown> = {
            mdm_vendor: r.mdm_vendor || null,
            data_platform: r.data_platform || null,
            product: r.product || null,
            product_version: r.product_version || null,
            domain: r.applyNew && r.proposed ? r.proposed.value : (r.domain || null),
            extension: r.extension || null,
            financial_model: r.financial_model || null,
            scope: r.scope || null,
          };
          if (r.applyNew && r.proposed) tags.new_subject = r.proposed;
          if (r.isUrl) form.append("url_meta", JSON.stringify(tags));
          else meta[r.filename] = tags;
        }
        if (Object.keys(meta).length) form.append("meta", JSON.stringify(meta));
      }

      const r = await api.upload(form);
      setMsg(null);
      setStatuses({});
      setReviews(null);
      setTracked(
        r.files
          .filter((f) => f.path)
          .map((f) => ({ path: f.path as string, label: f.url ?? (f.path as string).split("/").pop() ?? "file" })),
      );
      if (fileRef.current) fileRef.current.value = "";
      setUrl("");
    } catch (e) {
      setMsg({ ok: false, text: (e as Error).message });
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal title="Expand the knowledge base" onClose={onClose} width={560}>
      <p style={{ fontSize: 12, color: "var(--fg-3)", marginBottom: 14, lineHeight: 1.5 }}>
        Upload documents (PDF / Markdown / TXT), example scripts (.sql, .py, .json, …), or paste a reference URL.
        Use <strong>Scan &amp; classify</strong> to auto-tag each source by vendor, product and subject before
        ingesting — adjust anything before you confirm.
      </p>

      <label style={labelStyle}>Documents</label>
      <input
        ref={fileRef}
        type="file"
        multiple
        accept=".pdf,.md,.markdown,.txt,.sql,.py,.json,.yaml,.yml,.xml,.js,.ts,.tsx,.jsx,.sh,.bash,.scala,.java,.rb,.go,.csv,.tsv,.ini,.conf,.properties,.toml,.r"
        onChange={() => setReviews(null)}
        style={{ ...fieldStyle, padding: "7px 10px", marginBottom: 12 }}
      />

      <label style={labelStyle}>…or a reference URL</label>
      <input
        type="url"
        value={url}
        onChange={(e) => { setUrl(e.target.value); setCrawlReq("idle"); }}
        placeholder="https://docs.informatica.com/…"
        className="mono"
        style={{ ...fieldStyle, marginBottom: 6 }}
      />
      <div style={{ marginBottom: 14, padding: "8px 10px", background: "var(--accent-soft)", border: "1px solid var(--accent-border)", borderRadius: 8, fontSize: 11.5, color: "var(--fg-2)", lineHeight: 1.5 }}>
        A URL ingests <strong>only that single page</strong> — it does not crawl the rest of the site.{" "}
        {crawlReq === "done" ? (
          <span style={{ color: "var(--ok)", fontWeight: 500 }}>✓ Crawl requested — a steward will review it.</span>
        ) : (
          <>Need the whole site?{" "}
            <button type="button" onClick={requestSiteCrawl} disabled={crawlReq === "sending" || !url.trim()} className="hov-link"
              style={{ background: "none", border: 0, padding: 0, color: url.trim() ? "var(--accent-2)" : "var(--fg-4)", fontWeight: 600, fontSize: 11.5, textDecoration: "underline", cursor: url.trim() ? "pointer" : "default" }}>
              {crawlReq === "sending" ? "Requesting…" : "Request a full-site crawl"}
            </button>
            {!url.trim() && <span style={{ color: "var(--fg-4)" }}> (paste the site URL above first)</span>}
          </>
        )}
      </div>

      <details style={{ marginBottom: 12 }}>
        <summary style={{ ...labelStyle, marginBottom: 0, cursor: "pointer" }}>
          Default tags {reviews ? "(used for the URL + un-reviewed files)" : "(applied to all)"}
        </summary>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginTop: 10 }}>
          <div>
            <label style={labelStyle}>Vendor</label>
            <select value={vendor} onChange={(e) => { setVendor(e.target.value); setProduct(""); }} style={fieldStyle}>
              <option value="">—</option>
              {dims?.mdm_vendor.map((v) => <option key={v} value={v}>{cap(v)}</option>)}
            </select>
          </div>
          <div>
            <label style={labelStyle}>Data platform</label>
            <select value={platform} onChange={(e) => setPlatform(e.target.value)} style={fieldStyle}>
              <option value="">—</option>
              {dims?.data_platform.map((v) => <option key={v} value={v}>{cap(v)}</option>)}
            </select>
          </div>
          <div>
            <label style={labelStyle}>Product</label>
            <input list="product-suggestions" value={product} onChange={(e) => setProduct(e.target.value)} placeholder="e.g. Customer 360" style={fieldStyle} />
            <datalist id="product-suggestions">
              {productSuggestions.map((p) => <option key={p} value={p} />)}
            </datalist>
          </div>
          <div>
            <label style={labelStyle}>Version</label>
            <input value={version} onChange={(e) => setVersion(e.target.value)} placeholder="e.g. 10.5 / SaaS 2024.x" style={fieldStyle} />
          </div>
          <div>
            <label style={labelStyle}>Domain</label>
            <select value={domain} onChange={(e) => setDomain(e.target.value)} style={fieldStyle}>
              <option value="">—</option>
              {dims?.domain.map((v) => <option key={v} value={v}>{cap(v)}</option>)}
            </select>
          </div>
          <div>
            <label style={labelStyle}>Scope</label>
            <select value={scope} onChange={(e) => setScope(e.target.value)} style={fieldStyle}>
              <option value="vendor-specific">Vendor-specific</option>
              <option value="neutral">Neutral (shared)</option>
            </select>
          </div>
        </div>
      </details>

      {/* Per-file review (after Scan & classify) */}
      {reviews && reviews.length > 0 && (
        <div style={{ marginBottom: 14, border: "1px solid var(--border)", borderRadius: 10, overflow: "hidden" }}>
          <div style={{ padding: "8px 12px", background: "var(--bg-2)", borderBottom: "1px solid var(--border)", fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em" }}>
            Suggested tags · {reviews.length} file{reviews.length === 1 ? "" : "s"}
          </div>
          <div style={{ maxHeight: 280, overflowY: "auto", padding: 10, display: "flex", flexDirection: "column", gap: 10 }}>
            {reviews.map((r, i) => {
              const rowProducts = dims?.products?.[r.mdm_vendor] ?? [];
              return (
                <div key={r.filename} style={{ border: "1px solid var(--border)", borderRadius: 8, padding: 10, background: "var(--panel)" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8 }}>
                    <span className="mono" style={{ fontSize: 11.5, color: "var(--fg)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", flex: 1 }}>{r.filename}</span>
                    <span style={{ flexShrink: 0, fontSize: 10, fontWeight: 600, color: CONF_COLOR[r.confidence], border: `1px solid ${CONF_COLOR[r.confidence]}`, borderRadius: 999, padding: "1px 7px", textTransform: "uppercase", letterSpacing: "0.04em" }}>
                      {r.confidence}
                    </span>
                  </div>
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 7 }}>
                    <select value={r.mdm_vendor} onChange={(e) => updateRow(i, { mdm_vendor: e.target.value, product: "" })} style={miniField}>
                      <option value="">Vendor —</option>
                      {dims?.mdm_vendor.map((v) => <option key={v} value={v}>{cap(v)}</option>)}
                    </select>
                    <input list={`pl-${i}`} value={r.product} onChange={(e) => updateRow(i, { product: e.target.value })} placeholder="Product" style={miniField} />
                    <datalist id={`pl-${i}`}>{rowProducts.map((p) => <option key={p} value={p} />)}</datalist>
                    <select value={r.domain} onChange={(e) => updateRow(i, { domain: e.target.value, applyNew: false })} style={miniField} disabled={r.applyNew}>
                      <option value="">Subject —</option>
                      {dims?.domain.map((v) => <option key={v} value={v}>{cap(v)}</option>)}
                    </select>
                    <input value={r.product_version} onChange={(e) => updateRow(i, { product_version: e.target.value })} placeholder="Version" style={miniField} />
                    <select value={r.extension} onChange={(e) => updateRow(i, { extension: e.target.value })} style={miniField}>
                      <option value="">Extension — core (none)</option>
                      {(dims?.extension ?? []).map((v) => <option key={v} value={v}>{cap(v)}</option>)}
                    </select>
                    <select value={r.financial_model} onChange={(e) => updateRow(i, { financial_model: e.target.value })} style={miniField}>
                      <option value="">Financial model — none</option>
                      {(dims?.financial_model ?? []).map((v) => <option key={v} value={v}>{cap(v)}</option>)}
                    </select>
                  </div>
                  {r.proposed && (
                    <label style={{ display: "flex", alignItems: "center", gap: 7, marginTop: 8, fontSize: 11.5, color: "var(--fg-2)", cursor: "pointer" }}>
                      <input type="checkbox" checked={r.applyNew} onChange={(e) => updateRow(i, { applyNew: e.target.checked })} />
                      Add new subject <strong style={{ color: "var(--accent-2)" }}>{r.proposed.label}</strong> &amp; tag this file with it
                    </label>
                  )}
                  {r.reasoning && <div style={{ marginTop: 6, fontSize: 11, color: "var(--fg-4)", lineHeight: 1.4 }}>{r.reasoning}</div>}
                </div>
              );
            })}
          </div>
        </div>
      )}

      <div style={{ display: "flex", gap: 8 }}>
        <button
          onClick={scanAndClassify}
          disabled={classifying || busy}
          className="hov-row"
          style={{
            flex: "0 0 auto", padding: "9px 14px", borderRadius: 8, color: "var(--fg)", border: "1px solid var(--border)",
            background: "var(--panel)", boxShadow: "var(--shadow-sm)", fontSize: 13, fontWeight: 600, opacity: classifying || busy ? 0.5 : 1,
          }}
        >
          {classifying ? "Scanning…" : reviews ? "Re-scan" : "Scan & classify"}
        </button>
        <button
          onClick={submit}
          disabled={busy || classifying}
          className="hov-lift"
          style={{
            flex: 1, padding: "9px 12px", borderRadius: 8, color: "white", border: "1px solid oklch(0.48 0.18 33)",
            background: "linear-gradient(180deg, oklch(0.66 0.17 38), oklch(0.56 0.18 33))",
            fontSize: 13, fontWeight: 600, opacity: busy || classifying ? 0.5 : 1,
          }}
        >
          {busy ? "Uploading…" : reviews ? "Confirm & ingest" : "Upload & ingest"}
        </button>
      </div>

      {msg && (
        <div style={{ marginTop: 12, fontSize: 12.5, color: msg.ok ? "var(--ok)" : "var(--danger)" }}>{msg.text}</div>
      )}

      {tracked.length > 0 && (
        <div style={{ marginTop: 16, display: "flex", flexDirection: "column", gap: 10 }}>
          <div style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em" }}>
            Ingestion progress
          </div>
          {tracked.map((t) => {
            const st = statuses[t.path]?.status ?? "queued";
            const pct = st === "ready" ? 100 : st === "processing" ? 66 : st === "failed" ? 100 : 25;
            const color = st === "ready" ? "var(--ok)" : st === "failed" ? "var(--danger)" : "var(--accent)";
            const chunks = statuses[t.path]?.chunks ?? 0;
            const note =
              st === "ready"
                ? `Available · ${chunks} chunk${chunks === 1 ? "" : "s"}${statuses[t.path]?.needs_metadata ? " · needs tags" : ""}`
                : st === "failed" ? "Failed — check the file"
                : st === "processing" ? "Processing…" : "Queued…";
            return (
              <div key={t.path}>
                <div style={{ display: "flex", justifyContent: "space-between", gap: 8, fontSize: 11.5, marginBottom: 4 }}>
                  <span className="mono" style={{ color: "var(--fg-2)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", maxWidth: "62%" }}>{t.label}</span>
                  <span style={{ color, fontWeight: 500 }}>{note}</span>
                </div>
                <div style={{ height: 5, background: "var(--bg-3)", borderRadius: 3, overflow: "hidden" }}>
                  <div style={{ width: `${pct}%`, height: "100%", background: color, transition: "width .35s ease" }} />
                </div>
              </div>
            );
          })}
          <div style={{ fontSize: 11, color: "var(--fg-4)" }}>
            Content becomes answerable once each source reads “Available”.
          </div>
        </div>
      )}
    </Modal>
  );
}
