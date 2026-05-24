"use client";

import { api, type StewardshipTask } from "@/lib/api";
import type { SourceListItem, User } from "@/lib/types";
import { useEffect, useState } from "react";
import { Modal } from "./Modal";
import { DocTypeBadge, HierPill, Pill, subjectTone, vendorTone } from "./ui";
import { WikiBrowser } from "./WikiBrowser";

const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "");

type Stats = {
  wiki_pages: number; sources: number; chunks: number;
  by_vendor: { vendor: string; chunks: number }[];
  by_platform: { platform: string; chunks: number }[];
};

const TITLES: Record<string, string> = {
  sources: "Knowledge sources",
  wiki: "Browse knowledge",
  stats: "Data model explorer",
  stewardship: "Stewardship queue",
  audit: "Audit log",
};

export function BrowseModal({ view, user, onClose, onOpenSource, onOpenUpload }: {
  view: string; user: User; onClose: () => void; onOpenSource: (path: string) => void; onOpenUpload?: () => void;
}) {
  const [sources, setSources] = useState<SourceListItem[] | null>(null);
  const [stats, setStats] = useState<Stats | null>(null);
  const [tasks, setTasks] = useState<StewardshipTask[] | null>(null);
  const canReview = user.roles.includes("Admin") || user.roles.includes("Steward");

  useEffect(() => {
    if (view === "sources") api.sources().then((r) => setSources(r.sources)).catch(() => {});
    if (view === "stats") api.stats().then((s) => setStats(s as unknown as Stats)).catch(() => {});
    if (view === "stewardship") api.stewardship().then(setTasks).catch(() => {});
  }, [view]);

  const review = async (id: number, action: "approve" | "reject") => {
    await (action === "approve" ? api.approveTask(id) : api.rejectTask(id));
    api.stewardship().then(setTasks).catch(() => {});
  };

  const Bar = ({ label, value, max }: { label: string; value: number; max: number }) => (
    <div style={{ display: "grid", gridTemplateColumns: "120px 1fr auto", gap: 10, alignItems: "center", padding: "5px 0" }}>
      <span style={{ fontSize: 12.5 }}>{label}</span>
      <div style={{ height: 6, background: "var(--bg-3)", borderRadius: 3, overflow: "hidden" }}>
        <div style={{ width: `${(value / max) * 100}%`, height: "100%", background: "var(--accent)" }} />
      </div>
      <span className="mono" style={{ fontSize: 12, fontWeight: 600 }}>{value}</span>
    </div>
  );

  return (
    <Modal title={TITLES[view] ?? "Browse"} onClose={onClose} width={view === "wiki" ? 940 : 620}>
      {view === "wiki" && <WikiBrowser />}

      {view === "audit" && (
        <div style={{ fontSize: 12.5, color: "var(--fg-3)", lineHeight: 1.6 }}>
          Every wiki change and approval is recorded in the audit log and as a git commit. The full,
          filterable audit log is available to Admins and Stewards in the governance panel
          (<span className="mono">/admin</span>).
        </div>
      )}

      {view === "stats" && stats && (
        <div>
          <div style={{ display: "flex", gap: 10, marginBottom: 16 }}>
            {[["Wiki pages", stats.wiki_pages], ["Sources", stats.sources], ["Indexed chunks", stats.chunks]].map(([l, v]) => (
              <div key={l as string} style={{ flex: 1, padding: "12px 14px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 10 }}>
                <div className="mono" style={{ fontSize: 20, fontWeight: 600 }}>{v as number}</div>
                <div style={{ fontSize: 11.5, color: "var(--fg-3)" }}>{l as string}</div>
              </div>
            ))}
          </div>
          <div style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em", margin: "8px 0" }}>Chunks by MDM vendor</div>
          {stats.by_vendor.map((r) => <Bar key={r.vendor} label={r.vendor} value={r.chunks} max={stats.chunks} />)}
          <div style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em", margin: "14px 0 8px" }}>Chunks by data platform</div>
          {stats.by_platform.map((r) => <Bar key={r.platform} label={r.platform} value={r.chunks} max={stats.chunks} />)}
        </div>
      )}

      {view === "sources" && (
        <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
          {onOpenUpload && (
            <button onClick={onOpenUpload} className="hov-lift"
              style={{ display: "inline-flex", alignItems: "center", gap: 8, alignSelf: "flex-start", marginBottom: 4, padding: "7px 12px", borderRadius: 8, color: "white", border: "1px solid oklch(0.48 0.18 33)", background: "linear-gradient(180deg, oklch(0.66 0.17 38), oklch(0.56 0.18 33))", fontSize: 12.5, fontWeight: 600 }}>
              + Upload documentation
            </button>
          )}
          {!sources && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>Loading…</div>}
          {sources?.map((s) => (
            <button key={s.id} onClick={() => { if (s.kind === "wiki") onOpenSource(s.path); }} className="hov-row"
              style={{ display: "flex", alignItems: "center", gap: 10, padding: "8px 10px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 8, textAlign: "left", width: "100%" }}>
              <DocTypeBadge type={s.doc_type} />
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 12.5, fontWeight: 500, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{s.title}</div>
                <div style={{ fontSize: 11, color: "var(--fg-4)" }}>{s.path}</div>
              </div>
              <div style={{ display: "flex", gap: 4, flexShrink: 0 }}>
                {s.needs_metadata && <Pill size="xs" tone="warn">needs tags</Pill>}
                {s.mdm_vendor && <HierPill level={1} tone={vendorTone(s.mdm_vendor, 1)} label={cap(s.mdm_vendor)} />}
                {s.data_platform && <HierPill level={1} tone={vendorTone(s.data_platform, 1)} label={cap(s.data_platform)} />}
                {s.product && <HierPill level={2} tone={vendorTone(s.mdm_vendor ?? s.data_platform, 2)} label={`${s.product}${s.product_version ? ` ${s.product_version}` : ""}`} />}
                {s.domain && s.domain !== "general" && <HierPill level={3} dot={false} tone={subjectTone(s.domain, 2)} label={cap(s.domain)} />}
                {s.scope === "neutral" && <Pill size="xs" tone="ok">shared</Pill>}
              </div>
            </button>
          ))}
        </div>
      )}

      {view === "stewardship" && (
        <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
          {!tasks && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>Loading…</div>}
          {tasks?.length === 0 && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>No enrichment tasks yet. Say “capture this to the wiki” in a chat to create one.</div>}
          {tasks?.map((t) => (
            <div key={t.id} style={{ padding: "10px 12px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 8 }}>
              <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                <Pill size="xs" tone={t.status === "approved" ? "ok" : t.status === "rejected" ? "warn" : "accent"}>{t.status}</Pill>
                <span style={{ fontSize: 12.5, fontWeight: 500 }}>{t.type}</span>
                <span style={{ fontSize: 11.5, color: "var(--fg-4)", marginLeft: "auto" }}>{t.proposer?.name}</span>
              </div>
              <div style={{ fontSize: 12.5, color: "var(--fg-2)", marginTop: 6 }}>{t.summary}</div>
              {canReview && t.status === "pending" && (
                <div style={{ display: "flex", gap: 6, marginTop: 8 }}>
                  <button onClick={() => review(t.id, "approve")} style={{ padding: "5px 12px", borderRadius: 7, fontSize: 12, fontWeight: 500, color: "white", border: "1px solid oklch(0.48 0.18 33)", background: "var(--accent)" }}>Approve</button>
                  <button onClick={() => review(t.id, "reject")} style={{ padding: "5px 12px", borderRadius: 7, fontSize: 12, fontWeight: 500, color: "var(--fg-2)", border: "1px solid var(--border)", background: "var(--panel)" }}>Reject</button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </Modal>
  );
}
