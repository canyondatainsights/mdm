"use client";

import { apiOrigin } from "@/lib/api";
import { Fragment, type ReactNode } from "react";

/** Resolve a root-relative URL (e.g. an embedded /media/wiki image) against the API host. */
const resolveUrl = (u: string) => (u.startsWith("/") ? apiOrigin + u : u);

const isImageOnly = (l?: string) => !!l && /^\s*!\[[^\]]*\]\([^)]+\)\s*$/.test(l);

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

/** Inline: ![img](url), [link](url), **bold**, *italic*, ~~strike~~, `code`, [n] citations, bare URLs. */
function inline(text: string, onCite?: (n: number) => void): ReactNode[] {
  // Order matters: image (leading !) → link → bold → strike → italic → code → [n] citation → bare URL.
  const parts = text.split(
    /(!\[[^\]]*\]\([^)]+\)|\[[^\]]+\]\([^)]+\)|\*\*[^*]+\*\*|~~[^~]+~~|\*[^*\n]+\*|`[^`]+`|\[\d+\]|https?:\/\/[^\s<>()\]]+)/g,
  );
  return parts.map((p, i) => {
    const img = p.match(/^!\[([^\]]*)\]\(([^)]+)\)$/);
    if (img)
      return <img key={i} src={resolveUrl(img[2])} alt={img[1]} style={{ maxWidth: "100%", borderRadius: 6, verticalAlign: "middle" }} />;
    const link = p.match(/^\[([^\]]+)\]\(([^)]+)\)$/);
    if (link)
      return <a key={i} href={resolveUrl(link[2])} target="_blank" rel="noopener noreferrer" className="hov-link" style={{ color: "var(--accent-2)", textDecoration: "underline" }}>{link[1]}</a>;
    if (/^\*\*[^*]+\*\*$/.test(p))
      return <strong key={i} style={{ fontWeight: 600, color: "var(--fg)" }}>{p.slice(2, -2)}</strong>;
    if (/^~~[^~]+~~$/.test(p))
      return <span key={i} style={{ textDecoration: "line-through", color: "var(--fg-3)" }}>{p.slice(2, -2)}</span>;
    if (/^\*[^*\n]+\*$/.test(p))
      return <em key={i}>{p.slice(1, -1)}</em>;
    if (/^`[^`]+`$/.test(p))
      return <code key={i} className="mono" style={{ background: "var(--bg-3)", padding: "0 4px", borderRadius: 3, fontSize: "0.92em" }}>{p.slice(1, -1)}</code>;
    const m = p.match(/^\[(\d+)\]$/);
    if (m) return <CitationChip key={i} n={parseInt(m[1], 10)} onCite={onCite} />;
    if (/^https?:\/\//.test(p))
      return <a key={i} href={p} target="_blank" rel="noopener noreferrer" className="hov-link" style={{ color: "var(--accent-2)", textDecoration: "underline", wordBreak: "break-word" }}>{p}</a>;
    return <Fragment key={i}>{p}</Fragment>;
  });
}

const cells = (l: string) => l.replace(/^\s*\|/, "").replace(/\|\s*$/, "").split("|").map((c) => c.trim());
const isTableSep = (l?: string) => !!l && l.includes("|") && /^[\s|:-]*-[\s|:-]*$/.test(l);
const isBullet = (l?: string) => !!l && /^\s*[-*+]\s+/.test(l);
const isOrdered = (l?: string) => !!l && /^\s*\d+[.)]\s+/.test(l);
const isListItem = (l?: string) => isBullet(l) || isOrdered(l);
const isHeading = (l?: string) => !!l && /^#{1,6}\s+/.test(l);
const isQuote = (l?: string) => !!l && /^\s*>\s?/.test(l);
const indentOf = (l: string) => (l.match(/^(\s*)/)?.[1].replace(/\t/g, "  ").length ?? 0);

const HEADING: Record<number, { size: number; weight: number; mt: number }> = {
  1: { size: 22, weight: 700, mt: 8 },
  2: { size: 18, weight: 700, mt: 8 },
  3: { size: 15.5, weight: 600, mt: 6 },
  4: { size: 14, weight: 600, mt: 4 },
  5: { size: 13, weight: 600, mt: 2 },
  6: { size: 12.5, weight: 600, mt: 2 },
};

type LItem = { indent: number; ordered: boolean; content: string };

/** Render collected list items into nested <ul>/<ol> by indentation. */
function renderList(items: LItem[], onCite?: (n: number) => void): ReactNode {
  let pos = 0;
  const consume = (indent: number, key: number, nested: boolean): ReactNode => {
    const ordered = items[pos].ordered;
    const lis: ReactNode[] = [];
    let k = 0;
    while (pos < items.length && items[pos].indent === indent) {
      const it = items[pos];
      pos++;
      let sub: ReactNode = null;
      if (pos < items.length && items[pos].indent > indent) {
        sub = consume(items[pos].indent, 0, true);
      }
      lis.push(<li key={k++} style={{ paddingLeft: 2 }}>{inline(it.content, onCite)}{sub}</li>);
    }
    const Tag = ordered ? "ol" : "ul";
    return <Tag key={key} style={{ margin: nested ? "4px 0 0" : 0, paddingLeft: 20, display: "flex", flexDirection: "column", gap: 5 }}>{lis}</Tag>;
  };
  return consume(items[0].indent, 0, false);
}

/**
 * Line-based Markdown renderer: headings (h1–h6), GFM tables, fenced code/diagrams, blockquotes,
 * nested bullet/ordered lists, block images, paragraphs — with inline bold/italic/strike/code/links/
 * images/citations and autolinked URLs. Shared by chat answers and the wiki reader.
 */
export function Markdown({ text, onCite }: { text: string; onCite?: (n: number) => void }) {
  const lines = text.replace(/\r\n/g, "\n").split("\n");
  const out: ReactNode[] = [];
  let i = 0;
  let key = 0;

  while (i < lines.length) {
    const line = lines[i];

    if (line.trim() === "") { i++; continue; }

    // Fenced code block ```… — verbatim monospace, preserved whitespace (keeps ASCII/box diagrams aligned).
    const fence = line.match(/^\s*(`{3,}|~{3,})(\w*)\s*$/);
    if (fence) {
      const marker = fence[1][0];
      const code: string[] = [];
      i++;
      while (i < lines.length && !new RegExp(`^\\s*${marker}{3,}\\s*$`).test(lines[i])) {
        code.push(lines[i]);
        i++;
      }
      i++; // closing fence
      out.push(
        <pre key={key++} className="mono" style={{ margin: 0, padding: "12px 14px", background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: 8, overflowX: "auto", fontSize: 12.5, lineHeight: 1.5, color: "var(--fg-2)", whiteSpace: "pre" }}>
          {code.join("\n")}
        </pre>,
      );
      continue;
    }

    // Horizontal rule
    if (/^\s*(-{3,}|\*{3,}|_{3,})\s*$/.test(line)) {
      out.push(<hr key={key++} style={{ border: 0, borderTop: "1px solid var(--border)", margin: "2px 0" }} />);
      i++;
      continue;
    }

    // Heading (h1–h6 with distinct sizing)
    const h = line.match(/^(#{1,6})\s+(.+)$/);
    if (h) {
      const s = HEADING[h[1].length] ?? HEADING[6];
      out.push(
        <div key={key++} style={{ fontSize: s.size, fontWeight: s.weight, color: "var(--fg)", marginTop: s.mt, lineHeight: 1.3 }}>
          {inline(h[2].replace(/\s+#*\s*$/, ""), onCite)}
        </div>,
      );
      i++;
      continue;
    }

    // Blockquote
    if (isQuote(line)) {
      const quote: string[] = [];
      while (i < lines.length && isQuote(lines[i])) { quote.push(lines[i].replace(/^\s*>\s?/, "")); i++; }
      out.push(
        <blockquote key={key++} style={{ margin: 0, padding: "4px 14px", borderLeft: "3px solid var(--accent-border)", background: "var(--accent-soft)", borderRadius: "0 6px 6px 0", color: "var(--fg-2)" }}>
          {inline(quote.join(" "), onCite)}
        </blockquote>,
      );
      continue;
    }

    // Standalone image → block figure with optional caption.
    const imgOnly = line.match(/^\s*!\[([^\]]*)\]\(([^)]+)\)\s*$/);
    if (imgOnly) {
      out.push(
        <figure key={key++} style={{ margin: 0 }}>
          <img src={resolveUrl(imgOnly[2])} alt={imgOnly[1]} style={{ maxWidth: "100%", borderRadius: 8, border: "1px solid var(--border)", display: "block" }} />
          {imgOnly[1] && <figcaption style={{ fontSize: 11.5, color: "var(--fg-4)", marginTop: 5 }}>{imgOnly[1]}</figcaption>}
        </figure>,
      );
      i++;
      continue;
    }

    // GFM table: a row line followed by a |---|---| separator
    if (line.includes("|") && isTableSep(lines[i + 1])) {
      const header = cells(line);
      const cols = header.length;
      i += 2;
      const rows: string[][] = [];
      while (i < lines.length && lines[i].includes("|") && lines[i].trim() !== "") {
        const r = cells(lines[i]);
        rows.push(r.length === cols ? r : r.length < cols ? [...r, ...Array(cols - r.length).fill("")] : r.slice(0, cols));
        i++;
      }
      out.push(
        <div key={key++} style={{ overflowX: "auto", border: "1px solid var(--border)", borderRadius: 8 }}>
          <table style={{ borderCollapse: "collapse", width: "100%", minWidth: cols > 4 ? cols * 132 : undefined, fontSize: 13 }}>
            <thead>
              <tr>
                {header.map((c, k) => (
                  <th key={k} style={{ textAlign: "left", padding: "7px 11px", background: "var(--bg-2)", borderBottom: "1px solid var(--border-strong)", fontWeight: 600, color: "var(--fg)", whiteSpace: "nowrap" }}>{inline(c, onCite)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, k) => (
                <tr key={k}>
                  {r.map((c, m) => (
                    <td key={m} style={{ padding: "7px 11px", verticalAlign: "top", borderTop: "1px solid var(--border)", color: "var(--fg-2)" }}>{inline(c, onCite)}</td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>,
      );
      continue;
    }

    // Lists (bullet/ordered, nested by indentation)
    if (isListItem(line)) {
      const items: LItem[] = [];
      while (i < lines.length && isListItem(lines[i])) {
        const ordered = isOrdered(lines[i]);
        const content = lines[i].replace(/^\s*(?:[-*+]|\d+[.)])\s+/, "");
        items.push({ indent: indentOf(lines[i]), ordered, content });
        i++;
      }
      // Normalize to the shallowest indent so the root list starts at level 0.
      const base = Math.min(...items.map((it) => it.indent));
      for (const it of items) it.indent -= base;
      out.push(<Fragment key={key++}>{renderList(items, onCite)}</Fragment>);
      continue;
    }

    // Paragraph: consecutive plain lines until a blank/special line
    const para: string[] = [];
    while (
      i < lines.length && lines[i].trim() !== "" &&
      !isHeading(lines[i]) && !isListItem(lines[i]) && !isImageOnly(lines[i]) && !isQuote(lines[i]) &&
      !/^\s*(`{3,}|~{3,})/.test(lines[i]) &&
      !/^\s*(-{3,}|\*{3,}|_{3,})\s*$/.test(lines[i]) &&
      !(lines[i].includes("|") && isTableSep(lines[i + 1]))
    ) {
      para.push(lines[i]);
      i++;
    }
    if (para.length) {
      out.push(<p key={key++} style={{ margin: 0 }}>{inline(para.join(" "), onCite)}</p>);
    }
  }

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12, fontSize: 14, lineHeight: 1.65, color: "var(--fg-2)" }}>
      {out}
    </div>
  );
}
