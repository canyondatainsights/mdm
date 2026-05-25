"use client";

import { api } from "@/lib/api";
import type { Message, SharedConversation as Shared } from "@/lib/types";
import { useEffect, useState } from "react";
import { Markdown } from "@/components/Markdown";
import { SidecarFace, SidecarWordmark } from "@/components/Logo";
import { HierPill, Pill, subjectTone, vendorTone } from "@/components/ui";

const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "");
const msgText = (m: Message) =>
  Array.isArray(m.content) ? m.content.map((b) => ("text" in b ? b.text : "")).join("\n\n") : (m.content.text ?? "");

/** Read-only transcript for a public share link. */
export function SharedConversation({ token }: { token: string }) {
  const [conv, setConv] = useState<Shared | null>(null);
  const [error, setError] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.sharedConversation(token).then(setConv).catch(() => setError(true)).finally(() => setLoading(false));
  }, [token]);

  return (
    <div style={{ minHeight: "100vh", background: "var(--bg)", display: "flex", flexDirection: "column" }}>
      <div style={{ height: 56, borderBottom: "1px solid var(--border)", display: "flex", alignItems: "center", gap: 10, padding: "0 18px", background: "var(--bg-2)" }}>
        <SidecarFace size={26} />
        <SidecarWordmark size={19} />
        <span style={{ marginLeft: "auto", fontSize: 11.5, color: "var(--fg-4)" }}>Read-only shared conversation</span>
      </div>

      <div style={{ flex: 1, overflowY: "auto", display: "flex", justifyContent: "center" }}>
        <div style={{ width: "100%", maxWidth: 780, padding: "24px 24px 64px" }}>
          {loading && <div style={{ color: "var(--fg-4)", fontSize: 13 }}>Loading…</div>}
          {error && (
            <div style={{ textAlign: "center", color: "var(--fg-4)", padding: "60px 0" }}>
              <div style={{ fontSize: 15, fontWeight: 600, marginBottom: 6 }}>This shared link is no longer available</div>
              <div style={{ fontSize: 13 }}>It may have been revoked by its owner.</div>
            </div>
          )}

          {conv && (
            <>
              <h1 style={{ fontSize: 22, fontWeight: 700, color: "var(--fg)", margin: "0 0 10px" }}>{conv.title}</h1>
              <div style={{ display: "flex", gap: 5, flexWrap: "wrap", alignItems: "center", marginBottom: 20, paddingBottom: 14, borderBottom: "1px solid var(--border)" }}>
                {conv.mdm_vendor && <HierPill level={1} tone={vendorTone(conv.mdm_vendor, 1)} label={cap(conv.mdm_vendor)} />}
                {conv.data_platform && <HierPill level={1} tone={vendorTone(conv.data_platform, 1)} label={cap(conv.data_platform)} />}
                {conv.financial_model && <HierPill level={1} tone={vendorTone(conv.financial_model, 1)} label={cap(conv.financial_model)} />}
                {(conv.domains ?? []).filter((d) => d !== "general").map((d) => (
                  <HierPill key={d} level={3} dot={false} tone={subjectTone(d, 2)} label={cap(d)} />
                ))}
              </div>

              <div style={{ display: "flex", flexDirection: "column", gap: 18 }}>
                {conv.messages.map((m) =>
                  m.role === "user" ? (
                    <div key={m.id} style={{ display: "flex", justifyContent: "flex-end" }}>
                      <div style={{ maxWidth: "80%", background: "var(--accent)", color: "white", padding: "11px 14px", borderRadius: "14px 14px 4px 14px", fontSize: 14, lineHeight: 1.55 }}>
                        {msgText(m)}
                      </div>
                    </div>
                  ) : (
                    <div key={m.id} style={{ display: "flex", gap: 10 }}>
                      <div style={{ flexShrink: 0, marginTop: 2 }}><SidecarFace size={26} /></div>
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <Markdown text={msgText(m)} />
                        {m.confidence && (
                          <div style={{ marginTop: 8 }}>
                            <Pill size="xs" tone={m.confidence === "high" ? "ok" : m.confidence === "low" ? "warn" : "neutral"}>
                              {m.confidence} confidence
                            </Pill>
                          </div>
                        )}
                      </div>
                    </div>
                  ),
                )}
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
