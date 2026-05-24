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

/** Inline: ![img](url), [link](url), **bold**, `code`, and [n] citation chips. */
function inline(text: string, onCite?: (n: number) => void): ReactNode[] {
  // Order matters: image (leading !) before link, link before the bare [n] citation.
  const parts = text.split(/(!\[[^\]]*\]\([^)]+\)|\[[^\]]+\]\([^)]+\)|\*\*[^*]+\*\*|`[^`]+`|\[\d+\])/g);
  return parts.map((p, i) => {
    const img = p.match(/^!\[([^\]]*)\]\(([^)]+)\)$/);
    if (img)
      return <img key={i} src={resolveUrl(img[2])} alt={img[1]} style={{ maxWidth: "100%", borderRadius: 6, verticalAlign: "middle" }} />;
    const link = p.match(/^\[([^\]]+)\]\(([^)]+)\)$/);
    if (link)
      return <a key={i} href={resolveUrl(link[2])} target="_blank" rel="noopener noreferrer" className="hov-link" style={{ color: "var(--accent-2)", textDecoration: "underline" }}>{link[1]}</a>;
    if (/^\*\*[^*]+\*\*$/.test(p))
      return <strong key={i} style={{ fontWeight: 600, color: "var(--fg)" }}>{p.slice(2, -2)}</strong>;
    if (/^`[^`]+`$/.test(p))
      return <code key={i} className="mono" style={{ background: "var(--bg-3)", padding: "0 4px", borderRadius: 3, fontSize: "0.92em" }}>{p.slice(1, -1)}</code>;
    const m = p.match(/^\[(\d+)\]$/);
    if (m) return <CitationChip key={i} n={parseInt(m[1], 10)} onCite={onCite} />;
    return <Fragment key={i}>{p}</Fragment>;
  });
}

const cells = (l: string) => l.replace(/^\s*\|/, "").replace(/\|\s*$/, "").split("|").map((c) => c.trim());
const isTableSep = (l?: string) => !!l && l.includes("|") && /^[\s|:-]*-[\s|:-]*$/.test(l);
const isBullet = (l?: string) => !!l && /^\s*[-*]\s+/.test(l);
const isOrdered = (l?: string) => !!l && /^\s*\d+\.\s+/.test(l);
const isHeading = (l?: string) => !!l && /^#{1,6}\s+/.test(l);

/**
 * Line-based Markdown: headings, GFM tables, bullet/ordered lists, paragraphs, with
 * inline bold/code/citations. Parses per line (not per blank-line block) so a heading
 * immediately followed by a table — which the model emits — renders correctly.
 */
export function Markdown({ text, onCite }: { text: string; onCite?: (n: number) => void }) {
  const lines = text.replace(/\r\n/g, "\n").split("\n");
  const out: ReactNode[] = [];
  let i = 0;
  let key = 0;

  while (i < lines.length) {
    const line = lines[i];

    if (line.trim() === "") { i++; continue; }

    // Horizontal rule (section divider)
    if (/^\s*(-{3,}|\*{3,}|_{3,})\s*$/.test(line)) {
      out.push(<hr key={key++} style={{ border: 0, borderTop: "1px solid var(--border)", margin: "2px 0" }} />);
      i++;
      continue;
    }

    // Heading
    const h = line.match(/^(#{1,6})\s+(.+)$/);
    if (h) {
      const lvl = h[1].length;
      out.push(
        <div key={key++} style={{ fontSize: lvl <= 2 ? 15 : 14, fontWeight: 600, color: "var(--fg)", marginTop: 2 }}>
          {inline(h[2], onCite)}
        </div>,
      );
      i++;
      continue;
    }

    // Standalone image (e.g. an embedded diagram) → block figure with optional caption.
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
        // Normalize ragged rows to the header width so columns never shift.
        rows.push(r.length === cols ? r : r.length < cols ? [...r, ...Array(cols - r.length).fill("")] : r.slice(0, cols));
        i++;
      }
      out.push(
        <div key={key++} style={{ overflowX: "auto", border: "1px solid var(--border)", borderRadius: 8 }}>
          {/* Wide tables (mappings) get a min width so columns aren't crammed; the wrapper scrolls. */}
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

    // Unordered list
    if (isBullet(line)) {
      const items: string[] = [];
      while (i < lines.length && isBullet(lines[i])) { items.push(lines[i].replace(/^\s*[-*]\s+/, "")); i++; }
      out.push(
        <ul key={key++} style={{ margin: 0, paddingLeft: 20, display: "flex", flexDirection: "column", gap: 6 }}>
          {items.map((it, j) => <li key={j}>{inline(it, onCite)}</li>)}
        </ul>,
      );
      continue;
    }

    // Ordered list
    if (isOrdered(line)) {
      const items: string[] = [];
      while (i < lines.length && isOrdered(lines[i])) { items.push(lines[i].replace(/^\s*\d+\.\s+/, "")); i++; }
      out.push(
        <ol key={key++} style={{ margin: 0, paddingLeft: 20, display: "flex", flexDirection: "column", gap: 6 }}>
          {items.map((it, j) => <li key={j} style={{ paddingLeft: 4 }}>{inline(it, onCite)}</li>)}
        </ol>,
      );
      continue;
    }

    // Paragraph: consecutive plain lines (soft-wrapped) until a blank/special line
    const para: string[] = [];
    while (
      i < lines.length && lines[i].trim() !== "" &&
      !isHeading(lines[i]) && !isBullet(lines[i]) && !isOrdered(lines[i]) && !isImageOnly(lines[i]) &&
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
    <div style={{ display: "flex", flexDirection: "column", gap: 12, fontSize: 14, lineHeight: 1.6, color: "var(--fg-2)" }}>
      {out}
    </div>
  );
}
