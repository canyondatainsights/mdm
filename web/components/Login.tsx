"use client";

import { api, auth } from "@/lib/api";
import type { User } from "@/lib/types";
import { useState } from "react";
import { Icon } from "@/lib/icons";

export function Login({ onAuthed }: { onAuthed: (u: User) => void }) {
  const [email, setEmail] = useState("admin@canyondatainsights.com");
  const [password, setPassword] = useState("password");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true); setErr(null);
    try {
      const { token, user } = await api.login(email, password);
      auth.set(token);
      onAuthed(user);
    } catch (e) {
      setErr((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div style={{ height: "100%", display: "flex", alignItems: "center", justifyContent: "center", background: "var(--bg)" }}>
      <form onSubmit={submit} style={{ width: 360, background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 14, boxShadow: "var(--shadow)", padding: 24 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 18 }}>
          <div style={{ width: 32, height: 32, borderRadius: 8, color: "white", display: "inline-flex", alignItems: "center", justifyContent: "center", fontWeight: 700, fontSize: 14, background: "conic-gradient(from 210deg at 50% 50%, oklch(0.55 0.18 252), oklch(0.58 0.18 295), oklch(0.62 0.16 195), oklch(0.55 0.18 252))" }}>M</div>
          <div>
            <div style={{ fontWeight: 600, fontSize: 14 }}>MDM Knowledge Hub</div>
            <div style={{ fontSize: 11, color: "var(--fg-3)" }}>Sign in to continue</div>
          </div>
        </div>

        <label style={{ fontSize: 12, fontWeight: 600, color: "var(--fg-2)" }}>Email</label>
        <input value={email} onChange={(e) => setEmail(e.target.value)} type="email"
          style={{ width: "100%", padding: "9px 11px", borderRadius: 8, border: "1px solid var(--border)", margin: "6px 0 12px", fontSize: 13 }} />

        <label style={{ fontSize: 12, fontWeight: 600, color: "var(--fg-2)" }}>Password</label>
        <input value={password} onChange={(e) => setPassword(e.target.value)} type="password"
          style={{ width: "100%", padding: "9px 11px", borderRadius: 8, border: "1px solid var(--border)", margin: "6px 0 16px", fontSize: 13 }} />

        {err && <div style={{ color: "var(--danger)", fontSize: 12.5, marginBottom: 12 }}>{err}</div>}

        <button type="submit" disabled={busy}
          style={{ width: "100%", padding: "10px 12px", borderRadius: 9, color: "white", border: "1px solid oklch(0.40 0.16 258)", background: "linear-gradient(180deg, oklch(0.55 0.16 252), oklch(0.46 0.16 258))", fontSize: 13, fontWeight: 600, display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 8, opacity: busy ? 0.6 : 1 }}>
          <Icon name="sparkle" size={15} stroke={2} /> {busy ? "Signing in…" : "Sign in"}
        </button>
      </form>
    </div>
  );
}
