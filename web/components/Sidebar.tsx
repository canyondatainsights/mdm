"use client";

import { Icon } from "@/lib/icons";
import type { Conversation, User } from "@/lib/types";
import { useState } from "react";
import { HierPill, IconButton, TONE_MAP, subjectTone, vendorTone } from "./ui";
import { SidecarMark, SidecarWordmark, SidecarSlogan } from "./Logo";
import { ConfirmDialog } from "./ConfirmDialog";

const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "");

const NAV = [
  { key: "chat", icon: "sparkle", label: "Ask the hub", tone: "accent" },
  { key: "sources", icon: "book", label: "Knowledge sources", tone: "violet" },
  { key: "stats", icon: "graph", label: "Data model explorer", tone: "teal" },
  { key: "stewardship", icon: "flow", label: "Stewardship queue", tone: "amber" },
  { key: "audit", icon: "history", label: "Audit log", tone: "green" },
] as const;

export function Sidebar({
  user, conversations, activeId, onSelect, onNew, onNav, onLogout, view, onDelete,
}: {
  user: User; conversations: Conversation[]; activeId: number | null;
  onSelect: (id: number) => void; onNew: () => void; onNav: (key: string) => void;
  onLogout: () => void; view: string; onDelete: (id: number) => void;
}) {
  const [q, setQ] = useState("");
  const [hovered, setHovered] = useState<number | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Conversation | null>(null);
  const filtered = conversations.filter((c) => c.title.toLowerCase().includes(q.toLowerCase()));
  const initials = user.name.split(" ").map((w) => w[0]).slice(0, 2).join("").toUpperCase();

  return (
    <aside style={{ width: 280, flexShrink: 0, borderRight: "1px solid var(--border)", background: "var(--bg-2)", display: "flex", flexDirection: "column", height: "100%", position: "relative" }}>
      <div aria-hidden style={{ position: "absolute", top: 0, left: 0, right: 0, height: 220, pointerEvents: "none", zIndex: 0,
        background: "radial-gradient(120% 80% at 0% 0%, oklch(0.93 0.06 48 / 0.55), transparent 60%), radial-gradient(80% 70% at 100% 0%, oklch(0.92 0.06 32 / 0.40), transparent 60%)" }} />

      <div style={{ position: "relative", zIndex: 1, display: "flex", flexDirection: "column", flex: 1, minHeight: 0 }}>
        {/* brand */}
        <div style={{ padding: "14px 14px 10px", display: "flex", alignItems: "center", gap: 10 }}>
          <SidecarMark size={28} />
          <div style={{ display: "flex", flexDirection: "column", gap: 2, lineHeight: 1.1 }}>
            <SidecarWordmark size={20} />
            <SidecarSlogan size={11} />
          </div>
        </div>

        {/* new conversation */}
        <div style={{ padding: "4px 12px 10px" }}>
          <button onClick={onNew}
            style={{ width: "100%", display: "flex", alignItems: "center", gap: 8, padding: "9px 11px", color: "white", borderRadius: 9, fontSize: 13, fontWeight: 500,
              background: "linear-gradient(180deg, oklch(0.66 0.17 38), oklch(0.56 0.18 33))", border: "1px solid oklch(0.48 0.18 33)",
              boxShadow: "0 1px 0 oklch(0.99 0 0 / 0.30) inset, 0 6px 16px -6px oklch(0.48 0.18 33 / 0.55)" }}>
            <Icon name="plus" size={15} stroke={2} />
            <span>New conversation</span>
            <span className="mono" style={{ marginLeft: "auto", fontSize: 10.5, color: "oklch(0.99 0 0 / 0.75)", background: "oklch(0.99 0 0 / 0.15)", padding: "1px 5px", borderRadius: 4 }}>⌘N</span>
          </button>
        </div>

        {/* search */}
        <div style={{ padding: "0 12px 12px" }}>
          <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "7px 10px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 8, boxShadow: "var(--shadow-sm)" }}>
            <Icon name="search" size={14} style={{ color: "var(--fg-3)" }} />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search conversations"
              style={{ flex: 1, background: "transparent", border: 0, outline: "none", fontSize: 13, color: "var(--fg)" }} />
          </div>
        </div>

        {/* nav */}
        <div style={{ padding: "0 8px 6px", display: "flex", flexDirection: "column", gap: 1 }}>
          {NAV.map((item) => {
            const t = TONE_MAP[item.tone];
            const active = view === item.key;
            return (
              <button key={item.key} onClick={() => onNav(item.key)}
                style={{ display: "flex", alignItems: "center", gap: 10, padding: "6px 8px", borderRadius: 7, textAlign: "left",
                  background: active ? "var(--panel)" : "transparent", border: active ? "1px solid var(--border)" : "1px solid transparent",
                  boxShadow: active ? "var(--shadow-sm)" : "none", color: active ? "var(--fg)" : "var(--fg-2)", fontSize: 13, fontWeight: active ? 500 : 400 }}>
                <span style={{ width: 24, height: 24, borderRadius: 6, background: t.bg, color: t.fg, border: `1px solid ${t.bd}`, display: "inline-flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>
                  <Icon name={item.icon} size={13} stroke={1.9} />
                </span>
                <span>{item.label}</span>
              </button>
            );
          })}
        </div>

        {/* conversations */}
        <div style={{ flex: 1, overflowY: "auto", padding: "8px 8px 12px", minHeight: 0 }}>
          <div style={{ padding: "6px 10px 4px", fontSize: 10.5, fontWeight: 600, color: "var(--fg-4)", textTransform: "uppercase", letterSpacing: "0.08em" }}>Recent</div>
          {filtered.length === 0 && <div style={{ padding: "8px 10px", fontSize: 12, color: "var(--fg-4)" }}>No conversations yet.</div>}
          {filtered.map((c) => {
            const vt = vendorTone(c.mdm_vendor, 1);
            const subj = (c.domains ?? []).filter((d) => d !== "general")[0];
            const active = c.id === activeId;
            const showClose = hovered === c.id || active;
            return (
              <div key={c.id} onMouseEnter={() => setHovered(c.id)} onMouseLeave={() => setHovered((h) => (h === c.id ? null : h))}
                style={{ position: "relative", marginBottom: 1 }}>
                <button onClick={() => onSelect(c.id)}
                  style={{ position: "relative", width: "100%", display: "block", textAlign: "left", padding: "8px 30px 8px 14px", borderRadius: 7,
                    background: active ? "var(--panel)" : "transparent", border: active ? "1px solid var(--border)" : "1px solid transparent", boxShadow: active ? "var(--shadow-sm)" : "none" }}>
                  <span style={{ position: "absolute", left: 5, top: 10, bottom: 10, width: 3, borderRadius: 2, background: active ? vt.fg : vt.border }} />
                  <div style={{ fontSize: 13, fontWeight: active ? 500 : 400, color: "var(--fg)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", marginBottom: 3 }}>{c.title}</div>
                  <div style={{ display: "flex", alignItems: "center", gap: 4, flexWrap: "wrap" }}>
                    {c.mdm_vendor && <HierPill level={1} tone={vt} label={cap(c.mdm_vendor)} style={{ fontSize: 9.5, padding: "1px 6px" }} />}
                    {c.data_platform && <HierPill level={1} tone={vendorTone(c.data_platform, 1)} label={cap(c.data_platform)} style={{ fontSize: 9.5, padding: "1px 6px" }} />}
                    {subj && <HierPill level={3} dot={false} tone={subjectTone(subj, 2)} label={cap(subj)} style={{ fontSize: 9.5, padding: "1px 6px" }} />}
                  </div>
                </button>
                {showClose && (
                  <button
                    onClick={(e) => { e.stopPropagation(); setPendingDelete(c); }}
                    title="Delete conversation" aria-label="Delete conversation"
                    style={{ position: "absolute", right: 6, top: 7, width: 22, height: 22, display: "inline-flex", alignItems: "center", justifyContent: "center", borderRadius: 5, border: 0, background: "transparent", color: "var(--fg-4)" }}>
                    <Icon name="close" size={13} />
                  </button>
                )}
              </div>
            );
          })}
        </div>

        {/* user footer */}
        <div style={{ borderTop: "1px solid var(--border)", padding: "10px 12px", display: "flex", alignItems: "center", gap: 10, background: "linear-gradient(180deg, var(--bg-2), oklch(0.96 0.02 50))" }}>
          <div style={{ position: "relative" }}>
            <div style={{ width: 30, height: 30, borderRadius: "50%", background: "linear-gradient(135deg, oklch(0.66 0.16 42), oklch(0.58 0.16 35))", color: "white", display: "inline-flex", alignItems: "center", justifyContent: "center", fontSize: 11.5, fontWeight: 600 }}>{initials}</div>
            <span style={{ position: "absolute", right: -1, bottom: -1, width: 10, height: 10, borderRadius: "50%", background: "oklch(0.62 0.13 155)", border: "2px solid var(--bg-2)" }} />
          </div>
          <div style={{ display: "flex", flexDirection: "column", lineHeight: 1.2, minWidth: 0, flex: 1 }}>
            <span style={{ fontSize: 12.5, fontWeight: 500, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{user.name}</span>
            <span style={{ fontSize: 11, color: "var(--fg-3)" }}>{user.title ?? user.roles.join(", ")}</span>
          </div>
          {user.roles.includes("Admin") && <IconButton icon="settings" label="Settings" onClick={() => onNav("settings")} />}
          <IconButton icon="logout" label="Sign out" onClick={onLogout} />
        </div>
      </div>

      {pendingDelete && (
        <ConfirmDialog
          title="Delete conversation"
          message={<>Delete <strong style={{ color: "var(--fg)" }}>“{pendingDelete.title}”</strong>? This can&rsquo;t be undone.</>}
          confirmLabel="Delete"
          onConfirm={() => { onDelete(pendingDelete.id); setPendingDelete(null); }}
          onCancel={() => setPendingDelete(null)}
        />
      )}
    </aside>
  );
}
