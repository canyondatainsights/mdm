"use client";

import { api } from "@/lib/api";
import { Icon } from "@/lib/icons";
import type { Conversation, Dimensions, ResearchTopic, User } from "@/lib/types";
import { useEffect, useState } from "react";
import { Modal } from "./Modal";
import { ConfirmDialog } from "./ConfirmDialog";
import { HierPill, Pill, subjectTone, vendorTone } from "./ui";

const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "");

type Form = { id?: number; title: string; notes: string; scope: "private" | "shared"; mdm_vendor: string; data_platform: string; domains: string[] };

const blank = (c?: Conversation | null): Form => ({
  title: "", notes: "", scope: "private",
  mdm_vendor: c?.mdm_vendor ?? "", data_platform: c?.data_platform ?? "", domains: c?.domains?.filter((d) => d !== "general") ?? [],
});

/** Saved research topics — private + shared ("group research") — with deep-dive launch. */
export function ResearchModal({ user, activeConversation, onClose, onDeepDive }: {
  user: User; activeConversation: Conversation | null; onClose: () => void;
  onDeepDive: (conversationId: number, seed: string) => void;
}) {
  const [topics, setTopics] = useState<ResearchTopic[] | null>(null);
  const [dims, setDims] = useState<Dimensions | null>(null);
  const [form, setForm] = useState<Form | null>(null);
  const [pendingDelete, setPendingDelete] = useState<ResearchTopic | null>(null);
  const [busy, setBusy] = useState(false);

  const load = () => api.researchTopics().then(setTopics).catch(() => setTopics([]));
  useEffect(() => { load(); api.dimensions().then(setDims).catch(() => {}); }, []);

  const mine = (topics ?? []).filter((t) => t.owned);
  const shared = (topics ?? []).filter((t) => !t.owned && t.scope === "shared");

  const save = async () => {
    if (!form || !form.title.trim()) return;
    setBusy(true);
    const body = { title: form.title.trim(), notes: form.notes || null, scope: form.scope,
      mdm_vendor: form.mdm_vendor || null, data_platform: form.data_platform || null,
      domains: form.domains.length ? form.domains : null };
    try {
      if (form.id) await api.updateResearchTopic(form.id, body);
      else await api.createResearchTopic(body);
      setForm(null); await load();
    } catch { /* ignore */ } finally { setBusy(false); }
  };

  const dive = async (t: ResearchTopic) => {
    try { const r = await api.deepDiveTopic(t.id); onDeepDive(r.conversation_id, r.seed); } catch { /* ignore */ }
  };

  const card = (t: ResearchTopic) => (
    <div key={t.id} style={{ padding: "10px 12px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 9, marginBottom: 6 }}>
      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
        <span style={{ fontSize: 13, fontWeight: 600, color: "var(--fg)" }}>{t.title}</span>
        {t.scope === "shared" && <Pill size="xs" tone="accent">group</Pill>}
        {!t.owned && t.owner && <span style={{ fontSize: 11, color: "var(--fg-4)" }}>· {t.owner}</span>}
      </div>
      {t.notes && <div style={{ fontSize: 12, color: "var(--fg-3)", marginTop: 4, lineHeight: 1.5 }}>{t.notes}</div>}
      <div style={{ display: "flex", alignItems: "center", gap: 5, flexWrap: "wrap", marginTop: 8 }}>
        {t.mdm_vendor && <HierPill level={1} tone={vendorTone(t.mdm_vendor, 1)} label={cap(t.mdm_vendor)} />}
        {t.data_platform && <HierPill level={1} tone={vendorTone(t.data_platform, 1)} label={cap(t.data_platform)} />}
        {(t.domains ?? []).map((d) => <HierPill key={d} level={3} dot={false} tone={subjectTone(d, 2)} label={cap(d)} />)}
        <div style={{ marginLeft: "auto", display: "flex", gap: 6 }}>
          {t.owned && (
            <>
              <button onClick={() => setForm({ id: t.id, title: t.title, notes: t.notes ?? "", scope: t.scope, mdm_vendor: t.mdm_vendor ?? "", data_platform: t.data_platform ?? "", domains: t.domains ?? [] })}
                title="Edit" className="hov-icon" style={{ width: 26, height: 26, borderRadius: 6, border: "1px solid var(--border)", background: "var(--panel)", color: "var(--fg-3)", display: "inline-flex", alignItems: "center", justifyContent: "center" }}><Icon name="edit" size={13} /></button>
              <button onClick={() => setPendingDelete(t)} title="Delete" className="hov-icon" style={{ width: 26, height: 26, borderRadius: 6, border: "1px solid var(--border)", background: "var(--panel)", color: "var(--danger)", display: "inline-flex", alignItems: "center", justifyContent: "center" }}><Icon name="close" size={13} /></button>
            </>
          )}
          <button onClick={() => dive(t)} className="hov-lift" style={{ padding: "5px 11px", borderRadius: 7, fontSize: 12, fontWeight: 600, color: "white", border: "1px solid oklch(0.48 0.18 33)", background: "var(--accent)" }}>Deep-dive</button>
        </div>
      </div>
    </div>
  );

  const chip = (active: boolean, label: string, onClick: () => void) => (
    <button key={label} onClick={onClick} style={{ padding: "5px 10px", borderRadius: 999, fontSize: 12, fontWeight: 500,
      background: active ? "var(--accent-soft)" : "var(--panel)", color: active ? "var(--accent-2)" : "var(--fg-2)",
      border: `1px solid ${active ? "var(--accent-border)" : "var(--border)"}` }}>{label}</button>
  );

  return (
    <Modal title="Research topics" onClose={onClose} width={620}>
      {form ? (
        <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
          <input autoFocus placeholder="Topic title — e.g. DSAR across MDM" value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            style={{ fontSize: 14, padding: "9px 11px", border: "1px solid var(--border)", borderRadius: 8, background: "var(--bg-2)", color: "var(--fg)" }} />
          <textarea placeholder="Notes / what to explore (optional)" value={form.notes} rows={3}
            onChange={(e) => setForm({ ...form, notes: e.target.value })}
            style={{ fontSize: 13, padding: "9px 11px", border: "1px solid var(--border)", borderRadius: 8, background: "var(--bg-2)", color: "var(--fg)", resize: "vertical" }} />
          <div style={{ display: "flex", gap: 6 }}>
            {chip(form.scope === "private", "Private", () => setForm({ ...form, scope: "private" }))}
            {chip(form.scope === "shared", "Shared (group)", () => setForm({ ...form, scope: "shared" }))}
          </div>
          <div>
            <div style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em", marginBottom: 6 }}>Locked stack (optional)</div>
            <div style={{ display: "flex", gap: 8, marginBottom: 8 }}>
              <select value={form.mdm_vendor} onChange={(e) => setForm({ ...form, mdm_vendor: e.target.value })} style={{ flex: 1, fontSize: 12.5, padding: "7px 9px", border: "1px solid var(--border)", borderRadius: 8, background: "var(--bg-2)", color: "var(--fg)" }}>
                <option value="">Vendor (default)</option>
                {(dims?.mdm_vendor ?? []).map((v) => <option key={v} value={v}>{cap(v)}</option>)}
              </select>
              <select value={form.data_platform} onChange={(e) => setForm({ ...form, data_platform: e.target.value })} style={{ flex: 1, fontSize: 12.5, padding: "7px 9px", border: "1px solid var(--border)", borderRadius: 8, background: "var(--bg-2)", color: "var(--fg)" }}>
                <option value="">Platform (default)</option>
                {(dims?.data_platform ?? []).map((p) => <option key={p} value={p}>{cap(p)}</option>)}
              </select>
            </div>
            <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
              {(dims?.domain ?? []).filter((d) => d !== "general").map((d) =>
                chip(form.domains.includes(d), cap(d), () => setForm({ ...form, domains: form.domains.includes(d) ? form.domains.filter((x) => x !== d) : [...form.domains, d] })))}
            </div>
          </div>
          <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
            <button onClick={() => setForm(null)} style={{ padding: "8px 14px", borderRadius: 8, fontSize: 13, border: "1px solid var(--border)", background: "var(--panel)", color: "var(--fg-2)" }}>Cancel</button>
            <button onClick={save} disabled={busy || !form.title.trim()} className="hov-lift" style={{ padding: "8px 16px", borderRadius: 8, fontSize: 13, fontWeight: 600, color: "white", border: "1px solid oklch(0.48 0.18 33)", background: "var(--accent)", opacity: busy || !form.title.trim() ? 0.6 : 1 }}>{form.id ? "Save" : "Create topic"}</button>
          </div>
        </div>
      ) : (
        <div>
          <button onClick={() => setForm(blank(activeConversation))} className="hov-lift"
            style={{ display: "inline-flex", alignItems: "center", gap: 7, marginBottom: 12, padding: "7px 12px", borderRadius: 8, color: "white", border: "1px solid oklch(0.48 0.18 33)", background: "linear-gradient(180deg, oklch(0.66 0.17 38), oklch(0.56 0.18 33))", fontSize: 12.5, fontWeight: 600 }}>
            <Icon name="plus" size={14} /> New topic
          </button>
          {!topics && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>Loading…</div>}
          {topics && topics.length === 0 && <div style={{ fontSize: 12.5, color: "var(--fg-4)" }}>No research topics yet. Create one to save a thread for a deep-dive.</div>}
          {mine.length > 0 && <><div style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em", margin: "4px 0 8px" }}>My topics</div>{mine.map(card)}</>}
          {shared.length > 0 && <><div style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em", margin: "14px 0 8px" }}>Group research (shared)</div>{shared.map(card)}</>}
        </div>
      )}

      {pendingDelete && (
        <ConfirmDialog title="Delete topic"
          message={<>Delete <strong style={{ color: "var(--fg)" }}>“{pendingDelete.title}”</strong>?</>}
          confirmLabel="Delete"
          onConfirm={async () => { try { await api.deleteResearchTopic(pendingDelete.id); } catch {} setPendingDelete(null); load(); }}
          onCancel={() => setPendingDelete(null)} />
      )}
    </Modal>
  );
}
