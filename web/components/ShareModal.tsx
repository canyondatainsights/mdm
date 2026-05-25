"use client";

import { api } from "@/lib/api";
import { Icon } from "@/lib/icons";
import type { Conversation } from "@/lib/types";
import { useEffect, useState } from "react";
import { Modal } from "./Modal";

/** Create / copy / revoke a public read-only share link for a conversation. */
export function ShareModal({ conversation, onClose, onChanged }: {
  conversation: Conversation; onClose: () => void; onChanged: () => void;
}) {
  const [token, setToken] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [copied, setCopied] = useState(false);

  // If already shared, fetch the existing token (share() is idempotent).
  useEffect(() => {
    if (!conversation.shared) return;
    setBusy(true);
    api.shareConversation(conversation.id).then((r) => setToken(r.token)).catch(() => {}).finally(() => setBusy(false));
  }, [conversation.id, conversation.shared]);

  const url = token ? `${window.location.origin}/share/${token}` : "";
  const create = async () => {
    setBusy(true);
    try { setToken((await api.shareConversation(conversation.id)).token); onChanged(); } catch { /* ignore */ } finally { setBusy(false); }
  };
  const revoke = async () => {
    setBusy(true);
    try { await api.unshareConversation(conversation.id); setToken(null); onChanged(); } catch { /* ignore */ } finally { setBusy(false); }
  };
  const copy = async () => { try { await navigator.clipboard.writeText(url); setCopied(true); setTimeout(() => setCopied(false), 1500); } catch { /* ignore */ } };

  const subject = encodeURIComponent(`Sidecar — ${conversation.title}`);
  const body = encodeURIComponent(`I'm sharing a Sidecar conversation with you:\n\n${url}`);
  const mailto = `mailto:?subject=${subject}&body=${body}`;
  const teams = `https://teams.microsoft.com/share?href=${encodeURIComponent(url)}&msgText=${encodeURIComponent(conversation.title)}`;

  const btn: React.CSSProperties = { display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 12px", borderRadius: 8, fontSize: 12.5, fontWeight: 500, border: "1px solid var(--border)", background: "var(--panel)", color: "var(--fg-2)" };

  return (
    <Modal title="Share conversation" onClose={onClose} width={520}>
      <p style={{ fontSize: 12.5, color: "var(--fg-3)", marginTop: 0, marginBottom: 14, lineHeight: 1.5 }}>
        Create a read-only link to <strong style={{ color: "var(--fg)" }}>“{conversation.title}”</strong>.
      </p>

      {!token ? (
        <button onClick={create} disabled={busy} className="hov-lift"
          style={{ display: "inline-flex", alignItems: "center", gap: 8, padding: "9px 14px", borderRadius: 9, color: "white", border: "1px solid oklch(0.48 0.18 33)", background: "linear-gradient(180deg, oklch(0.66 0.17 38), oklch(0.56 0.18 33))", fontSize: 13, fontWeight: 600, opacity: busy ? 0.6 : 1 }}>
          <Icon name="share" size={15} /> {busy ? "Creating…" : "Create share link"}
        </button>
      ) : (
        <>
          <div style={{ display: "flex", gap: 6, marginBottom: 12 }}>
            <input readOnly value={url} onFocus={(e) => e.currentTarget.select()}
              style={{ flex: 1, fontSize: 12.5, padding: "8px 10px", border: "1px solid var(--border)", borderRadius: 8, background: "var(--bg-2)", color: "var(--fg-2)" }} />
            <button onClick={copy} style={{ ...btn, color: copied ? "var(--ok)" : "var(--fg-2)" }}>
              <Icon name={copied ? "check" : "copy"} size={14} /> {copied ? "Copied" : "Copy"}
            </button>
          </div>

          <div style={{ display: "flex", gap: 8, marginBottom: 16 }}>
            <a href={mailto} style={btn}><Icon name="link" size={14} /> Email</a>
            <a href={teams} target="_blank" rel="noopener noreferrer" style={btn}><Icon name="share" size={14} /> Teams</a>
            <button onClick={revoke} disabled={busy} style={{ ...btn, marginLeft: "auto", color: "var(--danger)", borderColor: "var(--border)" }}>
              <Icon name="close" size={14} /> Revoke
            </button>
          </div>
        </>
      )}

      <div style={{ display: "flex", gap: 8, alignItems: "flex-start", padding: "9px 11px", background: "oklch(0.96 0.05 80)", border: "1px solid oklch(0.87 0.07 75)", borderRadius: 8, fontSize: 11.5, color: "oklch(0.45 0.11 70)", lineHeight: 1.5 }}>
        <Icon name="shield" size={14} style={{ flexShrink: 0, marginTop: 1 }} />
        <span><strong>Anyone with this link can view</strong> the full transcript — no sign-in required. Don't share conversations containing sensitive data externally. Revoke any time.</span>
      </div>
    </Modal>
  );
}
