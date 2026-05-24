"use client";

import { api } from "@/lib/api";
import { Icon } from "@/lib/icons";
import type { SourceDetail } from "@/lib/types";
import { type ReactNode, useEffect, useState } from "react";
import { DocTypeBadge, HierPill, IconButton, Pill, subjectTone, vendorTone } from "./ui";

const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "");

const TRUST_TONE = {
  high: { fg: "var(--ok)", bg: "oklch(0.97 0.03 155)", border: "oklch(0.88 0.05 155)" },
  medium: { fg: "var(--fg-2)", bg: "var(--bg-3)", border: "var(--border)" },
  low: { fg: "var(--warn)", bg: "oklch(0.97 0.04 80)", border: "oklch(0.90 0.05 80)" },
} as const;

export function Inspector({ path, onClose }: { path: string | null; onClose: () => void }) {
  const [src, setSrc] = useState<SourceDetail | null>(null);
  const [tab, setTab] = useState<"source" | "lineage" | "related">("source");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!path) { setSrc(null); return; }
    setLoading(true);
    api.source(path).then(setSrc).catch(() => setSrc(null)).finally(() => setLoading(false));
  }, [path]);

  const vt = vendorTone(src?.mdm_vendor ?? src?.data_platform, 2);

  return (
    <aside style={{ width: 380, flexShrink: 0, borderLeft: "1px solid var(--border)", background: "var(--bg-2)", display: "flex", flexDirection: "column", height: "100%" }}>
      <div style={{ padding: "12px 14px", borderBottom: "1px solid var(--border)", background: "var(--bg)" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 10 }}>
          <Icon name="book" size={14} style={{ color: "var(--fg-3)" }} />
          <span style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em" }}>Source inspector</span>
          <div style={{ marginLeft: "auto" }}><IconButton icon="panel" label="Close" onClick={onClose} /></div>
        </div>
        {src && (
          <div>
            <div style={{ display: "flex", alignItems: "flex-start", gap: 10 }}>
              <DocTypeBadge type={src.doc_type} />
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 4, lineHeight: 1.3 }}>{src.title}</div>
                <div style={{ fontSize: 11.5, color: "var(--fg-3)" }}>{src.path}{src.updated ? ` · ${src.updated}` : ""}</div>
                {src.origin && (
                  <a href={src.origin} target="_blank" rel="noopener noreferrer" className="hov-link"
                    style={{ display: "inline-flex", alignItems: "center", gap: 4, marginTop: 5, fontSize: 11.5, color: "var(--accent-2)", wordBreak: "break-all" }}>
                    <Icon name="external" size={12} /> {src.origin}
                  </a>
                )}
              </div>
            </div>
            {/* vendor → product → domain → extension hierarchy */}
            <div style={{ display: "flex", flexWrap: "wrap", gap: 5, marginTop: 10 }}>
              {src.mdm_vendor && <HierPill level={1} tone={vendorTone(src.mdm_vendor, 1)} label={cap(src.mdm_vendor)} />}
              {src.data_platform && <HierPill level={1} tone={vendorTone(src.data_platform, 1)} label={cap(src.data_platform)} />}
              {src.financial_model && <HierPill level={1} tone={vendorTone(src.financial_model, 1)} label={cap(src.financial_model)} />}
              {src.product && <HierPill level={2} tone={vt} label={src.product} />}
              {src.domain && src.domain !== "general" && <HierPill level={3} dot={false} tone={subjectTone(src.domain, 2)} label={cap(src.domain)} />}
              {src.extension && <HierPill level={4} tone={subjectTone(src.domain, 3)} label={cap(src.extension)} />}
            </div>
          </div>
        )}
        <div style={{ display: "flex", gap: 2, marginTop: 12, borderBottom: "1px solid var(--border)", marginLeft: -14, marginRight: -14, paddingLeft: 14, paddingRight: 14 }}>
          {(["source", "lineage", "related"] as const).map((id) => (
            <button key={id} onClick={() => setTab(id)} className="hov-link"
              style={{ padding: "8px 4px", marginRight: 14, marginBottom: -1, background: "transparent", border: 0, borderBottom: tab === id ? "2px solid var(--fg)" : "2px solid transparent", color: tab === id ? "var(--fg)" : "var(--fg-3)", fontSize: 12.5, fontWeight: tab === id ? 600 : 500 }}>
              {id === "source" ? "Excerpt" : id.charAt(0).toUpperCase() + id.slice(1)}
            </button>
          ))}
        </div>
      </div>

      <div style={{ flex: 1, overflowY: "auto", padding: 14 }}>
        {!path && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>Click a citation to inspect its source.</div>}
        {loading && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>Loading…</div>}
        {src && tab === "source" && (
          <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
            {src.trust && (() => {
              const tt = TRUST_TONE[src.trust.level];
              return (
                <div style={{ border: `1px solid ${tt.border}`, background: tt.bg, borderRadius: 10, padding: "10px 12px" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8 }}>
                    <Icon name="shield" size={14} style={{ color: tt.fg }} />
                    <span style={{ fontSize: 12.5, fontWeight: 600, color: tt.fg }}>Trust: {cap(src.trust.level)} · {src.trust.score}/100</span>
                    <div style={{ marginLeft: "auto", width: 72, height: 6, borderRadius: 3, background: "var(--bg-3)", overflow: "hidden" }}>
                      <div style={{ width: `${src.trust.score}%`, height: "100%", background: tt.fg }} />
                    </div>
                  </div>
                  <div style={{ display: "flex", flexDirection: "column", gap: 3 }}>
                    {src.trust.factors.map((f, i) => (
                      <div key={i} style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 11.5, color: f.ok ? "var(--fg-2)" : "var(--fg-4)" }}>
                        <Icon name={f.ok ? "check" : "close"} size={12} style={{ color: f.ok ? "var(--ok)" : "var(--fg-4)" }} />
                        {f.label}
                      </div>
                    ))}
                  </div>
                </div>
              );
            })()}
            <div style={{ display: "flex", flexWrap: "wrap", gap: 4 }}>
              <Pill size="xs" tone={src.scope === "neutral" ? "neutral" : "ok"} icon="shield">{src.scope === "neutral" ? "Shared" : "Stack-specific"}</Pill>
            </div>
            {src.excerpt.map((e, i) => (
              <div key={i} style={{ background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 10, overflow: "hidden" }}>
                {e.anchor && (
                  <div style={{ padding: "8px 12px", borderBottom: "1px solid var(--border)", background: "var(--bg-2)" }}>
                    <span className="mono" style={{ fontSize: 10.5, fontWeight: 600, color: "var(--fg-3)" }}>{e.anchor.replace(/^#+\s*/, "")}</span>
                  </div>
                )}
                <div style={{ padding: "12px 14px", fontSize: 13, lineHeight: 1.6, color: "var(--fg-2)", whiteSpace: "pre-wrap" }}>
                  {e.text.length > 600 ? e.text.slice(0, 600) + "…" : e.text}
                </div>
              </div>
            ))}
          </div>
        )}
        {src && tab === "lineage" && (() => {
          const steps: { kind: string; color: string; node: ReactNode; leaf?: boolean }[] = [];
          if (src.mdm_vendor) steps.push({ kind: "Vendor", color: vendorTone(src.mdm_vendor, 1).fg, node: <HierPill level={1} tone={vendorTone(src.mdm_vendor, 1)} label={cap(src.mdm_vendor)} /> });
          if (src.data_platform) steps.push({ kind: "Data platform", color: vendorTone(src.data_platform, 1).fg, node: <HierPill level={1} tone={vendorTone(src.data_platform, 1)} label={cap(src.data_platform)} /> });
          if (src.financial_model) steps.push({ kind: "Financial model", color: vendorTone(src.financial_model, 1).fg, node: <HierPill level={1} tone={vendorTone(src.financial_model, 1)} label={cap(src.financial_model)} /> });
          if (src.product) steps.push({ kind: "Product", color: vt.fg, node: <HierPill level={2} tone={vt} label={src.product} /> });
          if (src.domain && src.domain !== "general") steps.push({ kind: "Data domain", color: subjectTone(src.domain, 2).fg, node: <HierPill level={3} dot={false} tone={subjectTone(src.domain, 2)} label={cap(src.domain)} /> });
          if (src.extension) steps.push({ kind: "Extension", color: subjectTone(src.domain, 3).fg, node: <HierPill level={4} tone={subjectTone(src.domain, 3)} label={cap(src.extension)} /> });
          steps.push({ kind: "Document", color: "var(--fg)", leaf: true, node: <span style={{ fontSize: 12.5, fontWeight: 600, color: "var(--fg)", wordBreak: "break-word" }}>{src.title}</span> });

          return (
            <div>
              <div>
                {steps.map((s, i) => {
                  const last = i === steps.length - 1;
                  return (
                    <div key={i} style={{ display: "flex", gap: 12, alignItems: "stretch" }}>
                      <div style={{ display: "flex", flexDirection: "column", alignItems: "center", width: 12 }}>
                        <span style={{ width: 10, height: 10, borderRadius: s.leaf ? 2 : "50%", background: s.color, marginTop: 3, flexShrink: 0, boxShadow: "0 0 0 3px var(--bg-2)" }} />
                        {!last && <span style={{ flex: 1, width: 2, background: "var(--border-strong)", minHeight: 14, margin: "2px 0" }} />}
                      </div>
                      <div style={{ paddingBottom: last ? 0 : 14, minWidth: 0 }}>
                        <div style={{ fontSize: 10, fontWeight: 600, textTransform: "uppercase", letterSpacing: "0.06em", color: "var(--fg-4)", marginBottom: 4 }}>{s.kind}</div>
                        {s.node}
                      </div>
                    </div>
                  );
                })}
              </div>
              <div style={{ fontSize: 11.5, color: "var(--fg-4)", marginTop: 12, lineHeight: 1.5 }}>
                Retrievable only in conversations locked to a matching stack.
              </div>
            </div>
          );
        })()}
        {src && tab === "related" && (
          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            {src.related.map((r) => (
              <div key={r.path} style={{ display: "flex", alignItems: "flex-start", gap: 10, padding: "10px 12px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 8 }}>
                <DocTypeBadge type={src.doc_type} />
                <div style={{ fontSize: 12.5, fontWeight: 500 }}>{r.title}</div>
              </div>
            ))}
            {src.related.length === 0 && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>No related pages.</div>}
          </div>
        )}
      </div>
    </aside>
  );
}
