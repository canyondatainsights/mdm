"use client";

import { api } from "@/lib/api";
import { Icon } from "@/lib/icons";
import type { WikiPageDetail, WikiPageSummary } from "@/lib/types";
import { useEffect, useMemo, useState } from "react";
import { Markdown } from "./Markdown";
import { HierPill, IconButton, Pill, subjectTone, vendorTone } from "./ui";

const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "");
/** "02-informatica-mdm" → "Informatica Mdm" section label. */
const sectionLabel = (s: string) =>
  s.replace(/^\d+[-_]?/, "").replace(/[-_]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase()) || "Other";

/** Browse + read curated wiki pages in the main viewer: section-grouped list + a roomy reader. */
export function WikiBrowser({ onToggleSidebar, sidebarCollapsed }: {
  onToggleSidebar?: () => void; sidebarCollapsed?: boolean;
}) {
  const [pages, setPages] = useState<WikiPageSummary[] | null>(null);
  const [sel, setSel] = useState<WikiPageDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [q, setQ] = useState("");

  useEffect(() => {
    api.wikiPages().then((r) => setPages(r.pages)).catch(() => setPages([]));
  }, []);

  const open = async (p: WikiPageSummary) => {
    if (sel?.path === p.path) return;
    setLoading(true);
    try { setSel(await api.wikiPage(p.path)); } catch { /* ignore */ } finally { setLoading(false); }
  };

  const groups = useMemo(() => {
    const f = (pages ?? []).filter((p) => {
      const hay = `${p.title} ${p.section ?? ""} ${p.tags.join(" ")}`.toLowerCase();
      return hay.includes(q.toLowerCase());
    });
    const g: Record<string, WikiPageSummary[]> = {};
    for (const p of f) (g[p.section ?? "other"] ??= []).push(p);
    return g;
  }, [pages, q]);
  const sections = Object.keys(groups).sort();

  return (
    <div style={{ flex: 1, display: "flex", flexDirection: "column", minWidth: 0, height: "100%", background: "var(--bg)" }}>
      {/* header */}
      <div style={{ height: 52, flexShrink: 0, borderBottom: "1px solid var(--border)", display: "flex", alignItems: "center", padding: "0 16px", gap: 10, background: "var(--bg)" }}>
        {onToggleSidebar && <IconButton icon="sidebar" label="Toggle sidebar" onClick={onToggleSidebar} active={!sidebarCollapsed} />}
        <div style={{ width: 1, height: 18, background: "var(--border)" }} />
        <Icon name="wiki" size={15} style={{ color: "var(--fg-3)" }} />
        <span style={{ fontSize: 13.5, fontWeight: 500 }}>Browse knowledge</span>
        {sel && (
          <>
            <Icon name="chevron-right" size={14} style={{ color: "var(--fg-4)" }} />
            <span style={{ fontSize: 13.5, color: "var(--fg-2)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{sel.title}</span>
          </>
        )}
      </div>

      {/* body: list + reader */}
      <div style={{ flex: 1, display: "flex", minHeight: 0 }}>
        {/* list */}
        <div style={{ width: 300, flexShrink: 0, borderRight: "1px solid var(--border)", display: "flex", flexDirection: "column", minHeight: 0, background: "var(--bg-2)" }}>
          <div style={{ padding: "12px 12px 8px" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "7px 10px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 8, boxShadow: "var(--shadow-sm)" }}>
              <Icon name="search" size={14} style={{ color: "var(--fg-3)" }} />
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search wiki"
                style={{ flex: 1, background: "transparent", border: 0, outline: "none", fontSize: 13, color: "var(--fg)" }} />
            </div>
          </div>
          <div style={{ flex: 1, overflowY: "auto", minHeight: 0, padding: "0 8px 12px", display: "flex", flexDirection: "column", gap: 8 }}>
            {!pages && <div style={{ fontSize: 12.5, color: "var(--fg-4)", padding: "4px 6px" }}>Loading…</div>}
            {pages && sections.length === 0 && <div style={{ fontSize: 12.5, color: "var(--fg-4)", padding: "4px 6px" }}>No pages found.</div>}
            {sections.map((s) => (
              <div key={s}>
                <div style={{ padding: "2px 6px 4px", fontSize: 10.5, fontWeight: 600, color: "var(--fg-4)", textTransform: "uppercase", letterSpacing: "0.07em" }}>{sectionLabel(s)}</div>
                {groups[s].map((p) => {
                  const active = sel?.path === p.path;
                  const subj = p.domain && p.domain !== "general" ? p.domain : null;
                  return (
                    <button key={p.id} onClick={() => open(p)} className="hov-row"
                      style={{ width: "100%", textAlign: "left", padding: "7px 9px", borderRadius: 7, marginBottom: 1,
                        background: active ? "var(--panel)" : "transparent", border: active ? "1px solid var(--border)" : "1px solid transparent", boxShadow: active ? "var(--shadow-sm)" : "none" }}>
                      <div style={{ fontSize: 12.5, fontWeight: active ? 600 : 500, color: "var(--fg)", marginBottom: 3 }}>{p.title}</div>
                      <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
                        {p.mdm_vendor && <HierPill level={1} tone={vendorTone(p.mdm_vendor, 1)} label={cap(p.mdm_vendor)} style={{ fontSize: 9.5, padding: "1px 6px" }} />}
                        {p.data_platform && <HierPill level={1} tone={vendorTone(p.data_platform, 1)} label={cap(p.data_platform)} style={{ fontSize: 9.5, padding: "1px 6px" }} />}
                        {subj && <HierPill level={3} dot={false} tone={subjectTone(subj, 2)} label={cap(subj)} style={{ fontSize: 9.5, padding: "1px 6px" }} />}
                      </div>
                    </button>
                  );
                })}
              </div>
            ))}
          </div>
        </div>

        {/* reader */}
        <div style={{ flex: 1, overflowY: "auto", minWidth: 0, display: "flex", justifyContent: "center" }}>
          <div style={{ width: "100%", maxWidth: 820, padding: "24px 28px 48px" }}>
            {!sel && !loading && (
              <div style={{ height: "60vh", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 8, color: "var(--fg-4)", textAlign: "center" }}>
                <Icon name="wiki" size={28} />
                <div style={{ fontSize: 13.5 }}>Select a page to read.</div>
                <div style={{ fontSize: 12, maxWidth: 360 }}>Wiki pages are curated, authored knowledge — the same content the assistant answers from.</div>
              </div>
            )}
            {loading && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>Loading…</div>}
            {sel && !loading && (
              <article>
                <h1 style={{ fontSize: 24, fontWeight: 700, color: "var(--fg)", margin: "0 0 10px" }}>{sel.title}</h1>
                <div style={{ display: "flex", gap: 5, flexWrap: "wrap", alignItems: "center", marginBottom: 18, paddingBottom: 14, borderBottom: "1px solid var(--border)" }}>
                  {sel.mdm_vendor && <HierPill level={1} tone={vendorTone(sel.mdm_vendor, 1)} label={cap(sel.mdm_vendor)} />}
                  {sel.data_platform && <HierPill level={1} tone={vendorTone(sel.data_platform, 1)} label={cap(sel.data_platform)} />}
                  {sel.product && <HierPill level={2} tone={vendorTone(sel.mdm_vendor ?? sel.data_platform, 2)} label={`${sel.product}${sel.product_version ? ` ${sel.product_version}` : ""}`} />}
                  {sel.domain && sel.domain !== "general" && <HierPill level={3} dot={false} tone={subjectTone(sel.domain, 2)} label={cap(sel.domain)} />}
                  {sel.scope === "neutral" && <Pill size="xs" tone="ok">shared</Pill>}
                  {sel.updated && <span style={{ fontSize: 11.5, color: "var(--fg-4)", marginLeft: "auto" }}>Updated {sel.updated}</span>}
                </div>
                <Markdown text={sel.body} />
              </article>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
