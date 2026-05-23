"use client";

import { Fragment, type ReactNode } from "react";

function CitationChip({ n, onCite }: { n: number; onCite?: (n: number) => void }) {
  return (
    <button
      onClick={() => onCite?.(n)}
      title={`Source ${n}`}
      aria-label={`Source ${n}`}
      style={{
        display: "inline-flex", alignItems: "center", height: 16, padding: "0 5px",
        background: "var(--accent-soft)", color: "var(--accent-2)",
        border: "1px solid var(--accent-border)", borderRadius: 4, fontSize: 10,
        fontWeight: 600, marginLeft: 2, lineHeight: 1, verticalAlign: "baseline",
      }}
    >
      {n}
    </button>
  );
}

/** Inline: **bold**, `code`, and [n] citation chips. */
function inline(text: string, onCite?: (n: number) => void): ReactNode[] {
  const parts = text.split(/(\*\*[^*]+\*\*|`[^`]+`|\[\d+\])/g);
  return parts.map((p, i) => {
    if (/^\*\*[^*]+\*\*$/.test(p))
      return <strong key={i} style={{ fontWeight: 600, color: "var(--fg)" }}>{p.slice(2, -2)}</strong>;
    if (/^`[^`]+`$/.test(p))
      return <code key={i} className="mono" style={{ background: "var(--bg-3)", padding: "0 4px", borderRadius: 3, fontSize: "0.92em" }}>{p.slice(1, -1)}</code>;
    const m = p.match(/^\[(\d+)\]$/);
    if (m) return <CitationChip key={i} n={parseInt(m[1], 10)} onCite={onCite} />;
    return <Fragment key={i}>{p}</Fragment>;
  });
}

export function Markdown({ text, onCite }: { text: string; onCite?: (n: number) => void }) {
  const blocks = text.replace(/\r\n/g, "\n").split(/\n{2,}/).filter((b) => b.trim() !== "");

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12, fontSize: 14, lineHeight: 1.6, color: "var(--fg-2)" }}>
      {blocks.map((block, i) => {
        const lines = block.split("\n");

        const h = block.match(/^(#{1,6})\s+(.+)$/);
        if (h) {
          const lvl = h[1].length;
          return (
            <div key={i} style={{ fontSize: lvl <= 2 ? 15 : 14, fontWeight: 600, color: "var(--fg)", marginTop: 2 }}>
              {inline(h[2], onCite)}
            </div>
          );
        }

        if (lines.every((l) => /^\s*[-*]\s+/.test(l))) {
          return (
            <ul key={i} style={{ margin: 0, paddingLeft: 20, display: "flex", flexDirection: "column", gap: 6 }}>
              {lines.map((l, j) => <li key={j}>{inline(l.replace(/^\s*[-*]\s+/, ""), onCite)}</li>)}
            </ul>
          );
        }

        if (lines.every((l) => /^\s*\d+\.\s+/.test(l))) {
          return (
            <ol key={i} style={{ margin: 0, paddingLeft: 20, display: "flex", flexDirection: "column", gap: 6 }}>
              {lines.map((l, j) => <li key={j} style={{ paddingLeft: 4 }}>{inline(l.replace(/^\s*\d+\.\s+/, ""), onCite)}</li>)}
            </ol>
          );
        }

        return <p key={i} style={{ margin: 0 }}>{inline(block, onCite)}</p>;
      })}
    </div>
  );
}
