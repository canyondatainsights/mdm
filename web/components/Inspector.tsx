"use client";

import { api } from "@/lib/api";
import { Icon } from "@/lib/icons";
import type { SourceDetail } from "@/lib/types";
import { useEffect, useState } from "react";
import { DocTypeBadge, IconButton, Pill } from "./ui";

export function Inspector({ path, onClose }: { path: string | null; onClose: () => void }) {
  const [src, setSrc] = useState<SourceDetail | null>(null);
  const [tab, setTab] = useState<"source" | "lineage" | "related">("source");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!path) return;
    setLoading(true);
    api.source(path).then(setSrc).catch(() => setSrc(null)).finally(() => setLoading(false));
  }, [path]);

  return (
    <aside style={{ width: 380, flexShrink: 0, borderLeft: "1px solid var(--border)", background: "var(--bg-2)", display: "flex", flexDirection: "column", height: "100%" }}>
      <div style={{ padding: "12px 14px", borderBottom: "1px solid var(--border)", background: "var(--bg)" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 10 }}>
          <Icon name="book" size={14} style={{ color: "var(--fg-3)" }} />
          <span style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em" }}>Source inspector</span>
          <div style={{ marginLeft: "auto" }}><IconButton icon="panel" label="Close" onClick={onClose} /></div>
        </div>
        {src && (
          <div style={{ display: "flex", alignItems: "flex-start", gap: 10 }}>
            <DocTypeBadge type={src.doc_type} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 4, lineHeight: 1.3 }}>{src.title}</div>
              <div style={{ fontSize: 11.5, color: "var(--fg-3)" }}>{src.path}{src.updated ? ` · Updated ${src.updated}` : ""}</div>
            </div>
          </div>
        )}
        <div style={{ display: "flex", gap: 2, marginTop: 12, borderBottom: "1px solid var(--border)", marginLeft: -14, marginRight: -14, paddingLeft: 14, paddingRight: 14 }}>
          {(["source", "lineage", "related"] as const).map((id) => (
            <button key={id} onClick={() => setTab(id)}
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
            <div style={{ display: "flex", flexWrap: "wrap", gap: 4 }}>
              {src.tags.map((t) => <Pill key={t} size="xs">{t}</Pill>)}
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
        {src && tab === "lineage" && (
          <div style={{ fontSize: 12.5, color: "var(--fg-2)", lineHeight: 1.6 }}>
            This source sits in the <strong>{src.mdm_vendor ?? "neutral"}</strong>
            {src.data_platform ? <> / <strong>{src.data_platform}</strong></> : null} stack, domain <strong>{src.domain}</strong>.
            It is only retrievable in conversations locked to a matching stack.
          </div>
        )}
        {src && tab === "related" && (
          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            {src.related.map((r) => (
              <div key={r.path} style={{ display: "flex", alignItems: "flex-start", gap: 10, padding: "10px 12px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 8 }}>
                <DocTypeBadge type="MD" />
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
