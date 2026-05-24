"use client";

import hljs from "highlight.js/lib/common";
import { useMemo } from "react";
import { MermaidDiagram } from "./MermaidDiagram";

const preStyle: React.CSSProperties = {
  margin: 0, padding: "12px 14px", background: "var(--bg-2)", border: "1px solid var(--border)",
  borderRadius: 8, overflowX: "auto", fontSize: 12.5, lineHeight: 1.5, color: "var(--fg-2)", whiteSpace: "pre",
};

/**
 * Fenced code block: ```mermaid → rendered diagram; a known language → syntax-highlighted
 * (highlight.js); otherwise verbatim monospace (keeps ASCII/box diagrams aligned).
 */
export function CodeBlock({ code, lang }: { code: string; lang?: string }) {
  const html = useMemo(() => {
    if (lang && lang !== "mermaid" && hljs.getLanguage(lang)) {
      try {
        return hljs.highlight(code, { language: lang, ignoreIllegals: true }).value;
      } catch {
        return null;
      }
    }
    return null;
  }, [code, lang]);

  if (lang === "mermaid") return <MermaidDiagram code={code} />;

  return (
    <pre className="mono" style={preStyle}>
      {html ? <code className="hljs" dangerouslySetInnerHTML={{ __html: html }} /> : <code>{code}</code>}
    </pre>
  );
}
