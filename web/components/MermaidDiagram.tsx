"use client";

import { useEffect, useState } from "react";

const rawStyle: React.CSSProperties = {
  margin: 0, padding: "12px 14px", background: "var(--bg-2)", border: "1px solid var(--border)",
  borderRadius: 8, overflowX: "auto", fontSize: 12.5, lineHeight: 1.5, color: "var(--fg-2)", whiteSpace: "pre",
};

/** Renders a ```mermaid fence into a real SVG diagram. Mermaid is lazy-loaded (browser only); on a
 *  parse error it falls back to the raw code so the page never breaks. */
export function MermaidDiagram({ code }: { code: string }) {
  const [svg, setSvg] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const mermaid = (await import("mermaid")).default;
        mermaid.initialize({ startOnLoad: false, theme: "neutral", securityLevel: "strict", fontFamily: "inherit" });
        const id = "m" + Math.random().toString(36).slice(2, 10);
        const { svg } = await mermaid.render(id, code.trim());
        if (!cancelled) { setSvg(svg); setFailed(false); }
      } catch {
        if (!cancelled) setFailed(true);
      }
    })();
    return () => { cancelled = true; };
  }, [code]);

  if (failed) return <pre className="mono" style={rawStyle}>{code}</pre>;
  if (!svg) return <div style={{ padding: 12, fontSize: 12, color: "var(--fg-4)" }}>Rendering diagram…</div>;
  return (
    <div
      className="mermaid-host"
      style={{ overflowX: "auto", padding: 12, background: "var(--panel)", border: "1px solid var(--border)", borderRadius: 8, display: "flex", justifyContent: "center" }}
      dangerouslySetInnerHTML={{ __html: svg }}
    />
  );
}
