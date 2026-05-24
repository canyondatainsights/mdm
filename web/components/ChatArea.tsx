"use client";

import { api, streamMessage } from "@/lib/api";
import { Icon } from "@/lib/icons";
import type { Citation, Conversation, Message } from "@/lib/types";
import { Component, type ReactNode, useEffect, useRef, useState } from "react";
import { Markdown } from "./Markdown";
import { SidecarFace } from "./Logo";
import { DocTypeBadge, HierPill, IconButton, Pill, subjectTone, vendorTone } from "./ui";

const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "—");

/** True if the markdown contains a GFM table (header row + |---| separator). */
const hasTable = (t: string) => {
  const ls = t.split("\n");
  return ls.some((l, i) => l.includes("|") && /^[\s|:-]*-[\s|:-]*$/.test(ls[i + 1] ?? ""));
};

/** Catches render exceptions in one message so a bad parse can't take down the whole app. */
class RenderBoundary extends Component<{ fallback: ReactNode; children: ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  componentDidCatch() { /* swallow — fallback renders the raw text */ }
  render() { return this.state.failed ? this.props.fallback : this.props.children; }
}

const plainText = (t: string) => (
  <div style={{ whiteSpace: "pre-wrap", fontSize: 14, lineHeight: 1.6, color: "var(--fg-2)" }}>{t}</div>
);

async function downloadXlsx(messageId: number) {
  const blob = await api.exportXlsx(messageId);
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `mapping-${messageId}.xlsx`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function msgText(m: Message): string {
  if (Array.isArray(m.content)) {
    return m.content.map((b) => ("text" in b ? b.text : "")).join("\n\n");
  }
  return m.content.text ?? "";
}

function SourcesBlock({ citations, onOpen }: { citations: Citation[]; onOpen: (path: string) => void }) {
  if (!citations?.length) return null;
  // One entry per source document (multiple cited passages collapse to a single line).
  const seen = new Set<string>();
  const unique = citations.filter((c) => {
    const key = c.origin || c.path;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
  return (
    <div style={{ marginTop: 14, padding: "10px 12px", background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: 10 }}>
      <div style={{ display: "flex", alignItems: "center", gap: 6, marginBottom: 8 }}>
        <Icon name="book" size={13} style={{ color: "var(--fg-3)" }} />
        <span style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em" }}>Sources</span>
      </div>
      <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
        {citations.map((c) => {
          const isUrl = !!c.origin && /^https?:\/\//.test(c.origin);
          const meta = [c.product, c.product_version, c.date].filter(Boolean).join(" · ");
          return (
            <button key={c.n} onClick={() => onOpen(c.path)}
              style={{ display: "flex", alignItems: "flex-start", gap: 10, padding: "8px 9px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 7, textAlign: "left", width: "100%" }}>
              <span style={{ flexShrink: 0, width: 18, height: 18, borderRadius: 4, background: "var(--accent-soft)", color: "var(--accent-2)", display: "inline-flex", alignItems: "center", justifyContent: "center", fontSize: 10.5, fontWeight: 700, border: "1px solid var(--accent-border)" }}>{c.n}</span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                  <DocTypeBadge type={c.doc_type ?? "MD"} />
                  <span style={{ fontSize: 12.5, fontWeight: 500, color: "var(--fg)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{c.title ?? c.path.replace(/^wiki\//, "")}</span>
                </div>
                {meta && <div style={{ fontSize: 11, color: "var(--fg-3)", marginTop: 2 }}>{meta}</div>}
                {c.anchor && <div style={{ fontSize: 11, color: "var(--fg-4)", marginTop: 2 }}>{c.anchor}</div>}
                {isUrl
                  ? <a href={c.origin!} target="_blank" rel="noreferrer" onClick={(e) => e.stopPropagation()} className="mono" style={{ fontSize: 10.5, color: "var(--accent-2)", marginTop: 2, display: "block", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{c.origin}</a>
                  : <div className="mono" style={{ fontSize: 10.5, color: "var(--fg-4)", marginTop: 2, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{c.origin ?? c.path}</div>}
              </div>
              <Icon name="external" size={13} style={{ color: "var(--fg-4)", marginTop: 2, flexShrink: 0 }} />
            </button>
          );
        })}
      </div>
    </div>
  );
}

function AssistantMessage({ text, citations, confidence, streaming, onOpenSource, messageId }: {
  text: string; citations: Citation[]; confidence?: string | null; streaming?: boolean; onOpenSource: (path: string) => void; messageId?: number;
}) {
  const citeByN = (n: number) => { const c = citations.find((x) => x.n === n); if (c) onOpenSource(c.path); };
  const [exporting, setExporting] = useState(false);
  const onExport = async () => {
    if (!messageId) return;
    setExporting(true);
    try { await downloadXlsx(messageId); } catch { /* ignore */ } finally { setExporting(false); }
  };
  return (
    <div style={{ display: "flex", padding: "12px 0", gap: 12 }}>
      <div style={{ flexShrink: 0, paddingTop: 1 }}>
        <SidecarFace size={28} />
      </div>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8 }}>
          <span style={{ fontSize: 13, fontWeight: 700, letterSpacing: "-0.01em" }}>Sidecar</span>
          {confidence && <Pill tone={confidence === "high" ? "ok" : confidence === "low" ? "warn" : "neutral"} size="xs" icon={confidence === "high" ? "check" : undefined}>{cap(confidence)} confidence</Pill>}
        </div>
        {text ? (
          // While streaming, render cheap plain text (re-parsing a growing Markdown
          // table on every token can freeze the tab). Full Markdown once complete.
          streaming
            ? plainText(text)
            : <RenderBoundary fallback={plainText(text)}><Markdown text={text} onCite={citeByN} /></RenderBoundary>
        ) : (
          <div style={{ display: "flex", gap: 4, padding: "4px 0" }}>
            {[0, 1, 2].map((i) => <span key={i} className="typing-dot" style={{ width: 6, height: 6, borderRadius: "50%", background: "var(--fg-4)" }} />)}
          </div>
        )}
        {!streaming && messageId && hasTable(text) && (
          <button onClick={onExport} disabled={exporting} className="hov-row"
            style={{ marginTop: 12, display: "inline-flex", alignItems: "center", gap: 7, padding: "7px 12px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 8, fontSize: 12.5, fontWeight: 500, color: "var(--fg-2)", opacity: exporting ? 0.6 : 1 }}>
            <Icon name="download" size={14} style={{ color: "var(--accent-2)" }} />
            {exporting ? "Preparing…" : "Download Excel"}
          </button>
        )}
        {!streaming && <SourcesBlock citations={citations} onOpen={onOpenSource} />}
      </div>
    </div>
  );
}

export function ChatArea({
  conversation, initialMessages, onOpenSource, onToggleSidebar, sidebarCollapsed,
  onToggleInspector, inspectorOpen, onChanged, onNeedKey, onNew, onAttach,
}: {
  conversation: Conversation | null; initialMessages: Message[];
  onOpenSource: (path: string) => void; onToggleSidebar: () => void; sidebarCollapsed: boolean;
  onToggleInspector: () => void; inspectorOpen: boolean; onChanged: () => void; onNeedKey: () => void; onNew: () => void;
  onAttach?: () => void;
}) {
  const [messages, setMessages] = useState<Message[]>(initialMessages);
  const [input, setInput] = useState("");
  const [streaming, setStreaming] = useState(false);
  const [streamText, setStreamText] = useState("");
  const [streamCites, setStreamCites] = useState<Citation[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [suggestions, setSuggestions] = useState<string[]>([]);
  const scrollRef = useRef<HTMLDivElement>(null);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    abortRef.current?.abort();   // cancel any in-flight stream when switching conversations
    setMessages(initialMessages); setStreamText(""); setStreamCites([]); setError(null);
    // Stack-aware starter questions for an empty conversation; follow-ups arrive via the SSE
    // 'suggestions' event after each answer. Clear on a conversation that already has history.
    setSuggestions([]);
    const id = conversation?.id;
    if (id && initialMessages.length === 0) {
      api.suggestions(id).then((r) => { if (conversation?.id === id) setSuggestions(r.questions ?? []); }).catch(() => {});
    }
  }, [conversation?.id, initialMessages]);
  useEffect(() => { scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight }); }, [messages, streamText]);

  const send = async (text: string) => {
    if (!conversation || !text.trim() || streaming) return;
    setInput("");
    setError(null);
    setMessages((m) => [...m, { id: Date.now(), role: "user", content: { text } }]);
    setStreaming(true); setStreamText(""); setStreamCites([]);
    const controller = new AbortController();
    abortRef.current = controller;
    let acc = "";
    try {
      await streamMessage(conversation.id, text, (e) => {
        if (e.type === "delta") { acc += e.text; setStreamText(acc); }
        else if (e.type === "done") {
          setMessages((m) => [...m, { id: e.message_id, role: "assistant", content: [{ type: "markdown", text: acc }], citations: e.citations, confidence: e.confidence as Message["confidence"] }]);
          setStreamText(""); setStreamCites([]); onChanged();
        } else if (e.type === "suggestions") { setSuggestions(e.questions ?? []); }
        else if (e.type === "meta") { /* sources_found available if needed */ }
        else if (e.type === "error") {
          setError(e.message);
          if (e.message.toLowerCase().includes("api key")) onNeedKey();
        }
      }, controller.signal);
    } catch (err) {
      // User pressed Stop: keep what streamed so far, no error.
      if (controller.signal.aborted || (err as Error).name === "AbortError") {
        if (acc.trim()) {
          setMessages((m) => [...m, { id: Date.now() + 1, role: "assistant", content: [{ type: "markdown", text: acc }], confidence: null }]);
        }
      } else {
        setError((err as Error).message);
      }
    } finally {
      setStreaming(false);
      setStreamText(""); setStreamCites([]);
      abortRef.current = null;
    }
  };

  const stop = () => abortRef.current?.abort();

  if (!conversation) {
    return (
      <div style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 14, background: "var(--bg)" }}>
        <div style={{ width: 44, height: 44, borderRadius: 12, background: "linear-gradient(135deg, var(--accent), var(--accent-2))", color: "white", display: "inline-flex", alignItems: "center", justifyContent: "center" }}><Icon name="sparkle" size={22} stroke={2} /></div>
        <div style={{ fontSize: 15, fontWeight: 600 }}>Start a conversation</div>
        <div style={{ fontSize: 13, color: "var(--fg-3)", maxWidth: 360, textAlign: "center" }}>Lock a technology stack first — answers stay within it and never mix vendors.</div>
        <button onClick={onNew} style={{ padding: "9px 16px", borderRadius: 9, color: "white", border: "1px solid oklch(0.48 0.18 33)", background: "linear-gradient(180deg, oklch(0.66 0.17 38), oklch(0.56 0.18 33))", fontSize: 13, fontWeight: 600 }}>New conversation</button>
      </div>
    );
  }

  return (
    <div style={{ flex: 1, display: "flex", flexDirection: "column", minWidth: 0, background: "var(--bg)" }}>
      {/* header */}
      <div style={{ height: 52, flexShrink: 0, borderBottom: "1px solid var(--border)", display: "flex", alignItems: "center", padding: "0 16px", gap: 10, background: "var(--bg)" }}>
        <IconButton icon="sidebar" label="Toggle sidebar" onClick={onToggleSidebar} active={!sidebarCollapsed} />
        <div style={{ width: 1, height: 18, background: "var(--border)" }} />
        <span style={{ fontSize: 13.5, fontWeight: 500, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{conversation.title}</span>
        <div style={{ marginLeft: "auto", display: "flex", alignItems: "center", gap: 6 }}>
          {conversation.pii_redacted && <Pill icon="shield" tone="ok" size="xs">PII redacted</Pill>}
          {conversation.mdm_vendor && <HierPill level={1} tone={vendorTone(conversation.mdm_vendor, 1)} label={cap(conversation.mdm_vendor)} />}
          {conversation.data_platform && <HierPill level={1} tone={vendorTone(conversation.data_platform, 1)} label={cap(conversation.data_platform)} />}
          {conversation.financial_model && <HierPill level={1} tone={vendorTone(conversation.financial_model, 1)} label={cap(conversation.financial_model)} />}
          {(conversation.domains ?? []).filter((d) => d !== "general").slice(0, 2).map((d) => (
            <HierPill key={d} level={3} dot={false} tone={subjectTone(d, 2)} label={cap(d)} />
          ))}
          <div style={{ width: 1, height: 18, background: "var(--border)", margin: "0 4px" }} />
          <IconButton icon="panel" label="Toggle sources panel" onClick={onToggleInspector} active={inspectorOpen} />
        </div>
      </div>

      {/* thread */}
      <div ref={scrollRef} style={{ flex: 1, overflowY: "auto", display: "flex", justifyContent: "center" }}>
        <div style={{ width: "100%", maxWidth: 760, padding: "20px 24px 8px", display: "flex", flexDirection: "column" }}>
          {messages.length === 0 && !streaming && (
            <div style={{ textAlign: "center", color: "var(--fg-4)", fontSize: 12.5, padding: "20px 0" }}>Ask anything within this locked stack.</div>
          )}
          {messages.map((m) =>
            m.role === "user" ? (
              <div key={m.id} style={{ display: "flex", justifyContent: "flex-end", padding: "8px 0" }}>
                <div style={{ maxWidth: "78%", display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 4 }}>
                  <div style={{ background: "var(--accent)", color: "white", padding: "11px 14px", borderRadius: "14px 14px 4px 14px", fontSize: 14, lineHeight: 1.55 }}>{msgText(m)}</div>
                </div>
              </div>
            ) : (
              <AssistantMessage key={m.id} messageId={m.id} text={msgText(m)} citations={m.citations ?? []} confidence={m.confidence} onOpenSource={onOpenSource} />
            )
          )}
          {streaming && <AssistantMessage text={streamText} citations={streamCites} streaming onOpenSource={onOpenSource} />}
          {error && (
            <div style={{ margin: "8px 0", padding: "10px 12px", background: "oklch(0.97 0.04 27)", border: "1px solid oklch(0.88 0.06 27)", borderRadius: 8, fontSize: 12.5, color: "oklch(0.45 0.16 27)" }}>
              {error}
            </div>
          )}
        </div>
      </div>

      {/* composer */}
      <div style={{ display: "flex", justifyContent: "center", background: "var(--bg)" }}>
        <div style={{ width: "100%", maxWidth: 760, padding: "0 24px 18px" }}>
          {suggestions.length > 0 && (
            <div style={{ padding: "4px 0 10px", display: "flex", flexWrap: "wrap", gap: 6 }}>
              {suggestions.map((p) => (
                <button key={p} onClick={() => send(p)} disabled={streaming} className="hov-chip"
                  style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 10px", background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 999, fontSize: 12.5, color: "var(--fg-2)", fontWeight: 500 }}>
                  <Icon name="sparkle" size={13} style={{ color: "var(--fg-3)" }} />
                  <span>{p}</span>
                </button>
              ))}
            </div>
          )}
          <div style={{ background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 14, boxShadow: "var(--shadow)", padding: "10px 12px 8px" }}>
            <textarea
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); send(input); } }}
              rows={2}
              placeholder="Ask about match rules, survivorship, lineage, pipelines, governance…"
              style={{ width: "100%", resize: "none", border: 0, outline: "none", background: "transparent", fontSize: 14, color: "var(--fg)", lineHeight: 1.5, minHeight: 44, fontFamily: "inherit" }}
            />
            <div style={{ display: "flex", alignItems: "center", gap: 6, marginTop: 4 }}>
              {onAttach && <IconButton icon="paperclip" label="Attach a source to the KB" onClick={onAttach} />}
              {conversation.mdm_vendor && <HierPill level={1} tone={vendorTone(conversation.mdm_vendor, 1)} label={cap(conversation.mdm_vendor)} />}
              {conversation.data_platform && <HierPill level={1} tone={vendorTone(conversation.data_platform, 1)} label={cap(conversation.data_platform)} />}
              <div style={{ marginLeft: "auto", display: "flex", alignItems: "center", gap: 8 }}>
                <span className="mono" style={{ fontSize: 11, color: "var(--fg-4)" }}>{input.length} / 8,000</span>
                {streaming ? (
                  <button onClick={stop} title="Stop generating" className="hov-lift"
                    style={{ width: 32, height: 32, display: "inline-flex", alignItems: "center", justifyContent: "center", border: 0, borderRadius: 8, background: "var(--fg)", color: "var(--bg)" }}>
                    <Icon name="stop" size={12} stroke={2.2} />
                  </button>
                ) : (
                  <button onClick={() => send(input)} disabled={!input.trim()} className="hov-lift"
                    style={{ width: 32, height: 32, display: "inline-flex", alignItems: "center", justifyContent: "center", border: 0, borderRadius: 8, background: input.trim() ? "var(--fg)" : "var(--bg-3)", color: input.trim() ? "var(--bg)" : "var(--fg-4)" }}>
                    <Icon name="arrow-up" size={15} stroke={2.2} />
                  </button>
                )}
              </div>
            </div>
          </div>
          <div style={{ textAlign: "center", fontSize: 11, color: "var(--fg-4)", marginTop: 8 }}>
            Answers are grounded in your governed MDM corpus, locked to this stack. Always verify before applying to production records.
          </div>
        </div>
      </div>
    </div>
  );
}
