"use client";

import { api } from "@/lib/api";
import type { SettingsInfo } from "@/lib/types";
import { useEffect, useState } from "react";
import { Modal } from "./Modal";
import { Pill } from "./ui";

export function SettingsModal({ onClose }: { onClose: () => void }) {
  const [info, setInfo] = useState<SettingsInfo | null>(null);
  const [key, setKey] = useState("");
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const load = () => api.settings().then(setInfo).catch((e) => setMsg({ ok: false, text: e.message }));
  useEffect(() => { load(); }, []);

  const save = async () => {
    setBusy(true); setMsg(null);
    try {
      await api.updateKey(key);
      setKey("");
      await load();
      setMsg({ ok: true, text: "API key saved." });
    } catch (e) { setMsg({ ok: false, text: (e as Error).message }); }
    finally { setBusy(false); }
  };

  const test = async () => {
    setBusy(true); setMsg(null);
    try {
      const r = await api.testKey(key || undefined);
      setMsg({ ok: r.ok, text: r.message });
    } catch (e) { setMsg({ ok: false, text: (e as Error).message }); }
    finally { setBusy(false); }
  };

  return (
    <Modal title="Settings · Claude API key" onClose={onClose} width={480}>
      <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 14 }}>
        <span style={{ fontSize: 12.5, color: "var(--fg-3)" }}>Status:</span>
        {info?.anthropic.has_key
          ? <Pill tone="ok" icon="check" size="xs">Key set · {info.anthropic.hint} ({info.anthropic.source})</Pill>
          : <Pill tone="warn" size="xs">No key — chat is disabled</Pill>}
      </div>

      <label style={{ fontSize: 12.5, fontWeight: 600, color: "var(--fg-2)", display: "block", marginBottom: 6 }}>
        Anthropic API key
      </label>
      <input
        type="password"
        value={key}
        onChange={(e) => setKey(e.target.value)}
        placeholder="sk-ant-…"
        className="mono"
        style={{
          width: "100%", padding: "9px 11px", borderRadius: 8, border: "1px solid var(--border)",
          background: "var(--panel)", fontSize: 12.5, color: "var(--fg)", marginBottom: 12,
        }}
      />

      <div style={{ display: "flex", gap: 8 }}>
        <button
          onClick={save}
          disabled={busy || key.length < 8}
          style={{
            flex: 1, padding: "9px 12px", borderRadius: 8, color: "white", border: "1px solid oklch(0.40 0.16 258)",
            background: "linear-gradient(180deg, oklch(0.55 0.16 252), oklch(0.46 0.16 258))",
            fontSize: 13, fontWeight: 600, opacity: busy || key.length < 8 ? 0.5 : 1,
          }}
        >
          Save key
        </button>
        <button
          onClick={test}
          disabled={busy}
          style={{
            padding: "9px 14px", borderRadius: 8, border: "1px solid var(--border)",
            background: "var(--panel)", color: "var(--fg-2)", fontSize: 13, fontWeight: 500, opacity: busy ? 0.5 : 1,
          }}
        >
          Test key
        </button>
      </div>

      {msg && (
        <div style={{ marginTop: 12, fontSize: 12.5, color: msg.ok ? "var(--ok)" : "var(--danger)" }}>
          {msg.text}
        </div>
      )}

      <p style={{ fontSize: 11, color: "var(--fg-4)", marginTop: 14, lineHeight: 1.5 }}>
        The key is stored encrypted on the server and is never returned to the browser. It is shared by all
        users of this workspace. Embeddings driver: <span className="mono">{info?.embeddings.driver}</span>.
      </p>
    </Modal>
  );
}
