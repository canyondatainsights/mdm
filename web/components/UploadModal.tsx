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

const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ");

export function UploadModal({ onClose, onUploaded }: { onClose: () => void; onUploaded?: () => void }) {
  const [dims, setDims] = useState<Dimensions | null>(null);
  const [vendor, setVendor] = useState("");
  const [platform, setPlatform] = useState("");
  const [product, setProduct] = useState("");
  const [version, setVersion] = useState("");
  const [domain, setDomain] = useState("");
  const [scope, setScope] = useState("vendor-specific");
  const [url, setUrl] = useState("");
  const [busy, setBusy] = useState(false);
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
      if (vendor) form.append("mdm_vendor", vendor);
      if (platform) form.append("data_platform", platform);
      if (product.trim()) form.append("product", product.trim());
      if (version.trim()) form.append("product_version", version.trim());
      if (domain) form.append("domain", domain);
      if (scope) form.append("scope", scope);

      const r = await api.upload(form);
      setMsg(null);
      setStatuses({});
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
    <Modal title="Expand the knowledge base" onClose={onClose} width={520}>
      <p style={{ fontSize: 12, color: "var(--fg-3)", marginBottom: 14, lineHeight: 1.5 }}>
        Upload PDF / Markdown / TXT documents, or paste a reference URL. Tag by vendor + product + version so
        the assistant can answer and cite within the right stack. Ingestion runs in the background.
      </p>

      <label style={labelStyle}>Documents</label>
      <input
        ref={fileRef}
        type="file"
        multiple
        accept=".pdf,.md,.markdown,.txt,application/pdf,text/plain,text/markdown"
        style={{ ...fieldStyle, padding: "7px 10px", marginBottom: 12 }}
      />

      <label style={labelStyle}>…or a reference URL</label>
      <input
        type="url"
        value={url}
        onChange={(e) => setUrl(e.target.value)}
        placeholder="https://docs.informatica.com/…"
        className="mono"
        style={{ ...fieldStyle, marginBottom: 14 }}
      />

      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginBottom: 12 }}>
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
          <input
            list="product-suggestions"
            value={product}
            onChange={(e) => setProduct(e.target.value)}
            placeholder="e.g. Customer 360"
            style={fieldStyle}
          />
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

      <button
        onClick={submit}
        disabled={busy}
        style={{
          width: "100%", padding: "9px 12px", borderRadius: 8, color: "white", border: "1px solid oklch(0.40 0.16 258)",
          background: "linear-gradient(180deg, oklch(0.55 0.16 252), oklch(0.46 0.16 258))",
          fontSize: 13, fontWeight: 600, opacity: busy ? 0.5 : 1,
        }}
      >
        {busy ? "Uploading…" : "Upload & ingest"}
      </button>

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
