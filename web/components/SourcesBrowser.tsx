"use client";

import { api } from "@/lib/api";
import type { Dimensions, SourceGroup, SourceListItem } from "@/lib/types";
import { useCallback, useEffect, useState } from "react";
import { DocTypeBadge, HierPill, Pill, subjectTone, vendorTone } from "./ui";

const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "");

const GROUP_OPTS: [string, string][] = [
  ["", "None"],
  ["domain", "Subject"],
  ["mdm_vendor", "Vendor"],
  ["data_platform", "Platform"],
  ["doc_type", "Type"],
];
const DOC_TYPES = ["PDF", "MD", "TXT", "URL", "CSV", "JSON", "SQL"];
const STATUSES: [string, string][] = [
  ["", "Any status"],
  ["ready", "Ready"],
  ["processing", "Processing"],
  ["queued", "Queued"],
  ["failed", "Failed"],
  ["needs_metadata", "Needs tags"],
  ["unapproved", "Unapproved"],
];
// Group dimension → the sources() filter param it maps to.
const GROUP_FILTER: Record<string, "domain" | "vendor" | "platform" | "doc_type"> = {
  domain: "domain",
  mdm_vendor: "vendor",
  data_platform: "platform",
  doc_type: "doc_type",
};

const PER_PAGE = 50;

type Filters = { doc_type: string; vendor: string; platform: string; domain: string; status: string };
type GroupState = { rows: SourceListItem[]; page: number; lastPage: number; total: number; loading: boolean };

const selStyle = {
  padding: "5px 7px", fontSize: 12, borderRadius: 6, border: "1px solid var(--border)",
  background: "var(--panel)", color: "var(--fg)", fontFamily: "inherit",
} as const;

export function SourcesBrowser({ onOpenSource, onOpenUpload }: {
  onOpenSource: (path: string) => void;
  onOpenUpload?: () => void;
}) {
  const [dims, setDims] = useState<Dimensions | null>(null);
  const [q, setQ] = useState("");
  const [debouncedQ, setDebouncedQ] = useState("");
  const [filters, setFilters] = useState<Filters>({ doc_type: "", vendor: "", platform: "", domain: "", status: "" });
  const [groupBy, setGroupBy] = useState("domain");

  // flat-list state
  const [list, setList] = useState<SourceListItem[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  // group state
  const [groups, setGroups] = useState<SourceGroup[] | null>(null);
  const [expanded, setExpanded] = useState<Record<string, GroupState>>({});

  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);

  useEffect(() => { api.dimensions().then(setDims).catch(() => {}); }, []);
  useEffect(() => { const t = setTimeout(() => setDebouncedQ(q), 300); return () => clearTimeout(t); }, [q]);

  const baseParams = useCallback(() => ({
    q: debouncedQ || undefined,
    doc_type: filters.doc_type || undefined,
    vendor: filters.vendor || undefined,
    platform: filters.platform || undefined,
    domain: filters.domain || undefined,
    status: filters.status || undefined,
  }), [debouncedQ, filters]);

  // (Re)load whenever the query, filters, or grouping change.
  useEffect(() => {
    let active = true;
    setLoading(true);
    setExpanded({});
    if (groupBy) {
      api.sources({ ...baseParams(), group_by: groupBy }).then((r) => {
        if (!active || r.mode !== "groups") return;
        setGroups(r.groups); setTotal(r.total); setLoading(false);
      }).catch(() => active && setLoading(false));
    } else {
      api.sources({ ...baseParams(), page: 1, per_page: PER_PAGE }).then((r) => {
        if (!active || r.mode !== "list") return;
        setList(r.sources); setPage(1); setLastPage(r.last_page); setTotal(r.total); setLoading(false);
      }).catch(() => active && setLoading(false));
    }
    return () => { active = false; };
  }, [groupBy, baseParams]);

  const loadMoreFlat = async () => {
    const next = page + 1;
    const r = await api.sources({ ...baseParams(), page: next, per_page: PER_PAGE });
    if (r.mode === "list") { setList((p) => [...p, ...r.sources]); setPage(next); }
  };

  const fetchGroup = async (key: string, nextPage: number): Promise<GroupState | null> => {
    const r = await api.sources({ ...baseParams(), [GROUP_FILTER[groupBy]]: key, page: nextPage, per_page: PER_PAGE });
    if (r.mode !== "list") return null;
    return { rows: r.sources, page: nextPage, lastPage: r.last_page, total: r.total, loading: false };
  };

  const toggleGroup = async (key: string) => {
    if (expanded[key]) { setExpanded(({ [key]: _drop, ...rest }) => rest); return; }
    setExpanded((p) => ({ ...p, [key]: { rows: [], page: 0, lastPage: 1, total: 0, loading: true } }));
    const g = await fetchGroup(key, 1);
    if (g) setExpanded((p) => ({ ...p, [key]: g }));
  };

  const loadMoreInGroup = async (key: string) => {
    const cur = expanded[key];
    if (!cur) return;
    const g = await fetchGroup(key, cur.page + 1);
    if (g) setExpanded((p) => ({ ...p, [key]: { ...g, rows: [...cur.rows, ...g.rows] } }));
  };

  const renderRow = (s: SourceListItem) => (
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
  );

  const setFilter = (patch: Partial<Filters>) => setFilters((f) => ({ ...f, ...patch }));

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
      {onOpenUpload && (
        <button onClick={onOpenUpload} className="hov-lift"
          style={{ display: "inline-flex", alignItems: "center", gap: 8, alignSelf: "flex-start", padding: "7px 12px", borderRadius: 8, color: "white", border: "1px solid oklch(0.48 0.18 33)", background: "linear-gradient(180deg, oklch(0.66 0.17 38), oklch(0.56 0.18 33))", fontSize: 12.5, fontWeight: 600 }}>
          + Upload documentation
        </button>
      )}

      {/* Toolbar: search + filters + group-by */}
      <div style={{ display: "flex", flexWrap: "wrap", gap: 6, alignItems: "center" }}>
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search title or path…"
          style={{ ...selStyle, flex: "1 1 180px", minWidth: 140 }} />
        <select value={filters.doc_type} onChange={(e) => setFilter({ doc_type: e.target.value })} style={selStyle}>
          <option value="">Type</option>
          {DOC_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
        </select>
        <select value={filters.vendor} onChange={(e) => setFilter({ vendor: e.target.value })} style={selStyle}>
          <option value="">Vendor</option>
          {dims?.mdm_vendor.map((v) => <option key={v} value={v}>{cap(v)}</option>)}
        </select>
        <select value={filters.platform} onChange={(e) => setFilter({ platform: e.target.value })} style={selStyle}>
          <option value="">Platform</option>
          {dims?.data_platform.map((v) => <option key={v} value={v}>{cap(v)}</option>)}
        </select>
        <select value={filters.domain} onChange={(e) => setFilter({ domain: e.target.value })} style={selStyle}>
          <option value="">Subject</option>
          {dims?.domain.map((v) => <option key={v} value={v}>{cap(v)}</option>)}
        </select>
        <select value={filters.status} onChange={(e) => setFilter({ status: e.target.value })} style={selStyle}>
          {STATUSES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
        <span style={{ marginLeft: "auto", display: "flex", alignItems: "center", gap: 5, fontSize: 11.5, color: "var(--fg-3)" }}>
          Group by
          <select value={groupBy} onChange={(e) => setGroupBy(e.target.value)} style={selStyle}>
            {GROUP_OPTS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </span>
      </div>

      <div style={{ fontSize: 11, color: "var(--fg-4)" }}>{loading ? "Loading…" : `${total.toLocaleString()} source${total === 1 ? "" : "s"}`}</div>

      {/* Grouped view */}
      {groupBy && groups && (
        <div style={{ display: "flex", flexDirection: "column", gap: 4 }}>
          {groups.map((g) => {
            const st = expanded[g.key];
            return (
              <div key={g.key}>
                <button onClick={() => toggleGroup(g.key)} className="hov-row"
                  style={{ display: "flex", alignItems: "center", gap: 8, width: "100%", padding: "7px 10px", background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: 8, textAlign: "left" }}>
                  <span style={{ fontSize: 11, color: "var(--fg-4)", width: 12 }}>{st ? "▾" : "▸"}</span>
                  <span style={{ fontSize: 12.5, fontWeight: 600, flex: 1 }}>{g.key === "—" ? "Unassigned" : cap(g.key)}</span>
                  <span className="mono" style={{ fontSize: 11.5, color: "var(--fg-3)" }}>{g.count.toLocaleString()}</span>
                </button>
                {st && (
                  <div style={{ display: "flex", flexDirection: "column", gap: 4, padding: "4px 0 6px 14px" }}>
                    {st.loading && st.rows.length === 0 && <div style={{ fontSize: 12, color: "var(--fg-4)" }}>Loading…</div>}
                    {st.rows.map(renderRow)}
                    {st.page < st.lastPage && (
                      <button onClick={() => loadMoreInGroup(g.key)} className="hov-row"
                        style={{ alignSelf: "flex-start", padding: "5px 10px", borderRadius: 7, fontSize: 11.5, color: "var(--fg-2)", border: "1px solid var(--border)", background: "var(--panel)" }}>
                        Load more ({st.total - st.rows.length} more)
                      </button>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* Flat paginated view */}
      {!groupBy && (
        <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
          {list.map(renderRow)}
          {!loading && list.length === 0 && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>No sources match.</div>}
          {page < lastPage && (
            <button onClick={loadMoreFlat} className="hov-row"
              style={{ alignSelf: "center", marginTop: 4, padding: "7px 16px", borderRadius: 8, fontSize: 12.5, fontWeight: 600, color: "var(--fg-2)", border: "1px solid var(--border)", background: "var(--panel)" }}>
              Load more — showing {list.length.toLocaleString()} of {total.toLocaleString()}
            </button>
          )}
        </div>
      )}
    </div>
  );
}
