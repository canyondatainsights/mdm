"use client";

import { Icon } from "@/lib/icons";
import type { CSSProperties, ReactNode } from "react";

type Tone = "neutral" | "accent" | "ok" | "warn";

const TONES: Record<Tone, { bg: string; fg: string; bd: string }> = {
  neutral: { bg: "var(--bg-3)", fg: "var(--fg-2)", bd: "var(--border)" },
  accent: { bg: "var(--accent-soft)", fg: "var(--accent-2)", bd: "var(--accent-border)" },
  ok: { bg: "oklch(0.96 0.04 155)", fg: "oklch(0.42 0.13 155)", bd: "oklch(0.86 0.06 155)" },
  warn: { bg: "oklch(0.97 0.04 80)", fg: "oklch(0.48 0.12 70)", bd: "oklch(0.88 0.06 80)" },
};

export function Pill({
  children, tone = "neutral", icon, size = "sm", style,
}: {
  children: ReactNode; tone?: Tone; icon?: string; size?: "xs" | "sm"; style?: CSSProperties;
}) {
  const t = TONES[tone];
  const fs = size === "xs" ? 11 : 12;
  return (
    <span
      style={{
        display: "inline-flex", alignItems: "center", gap: 4,
        background: t.bg, color: t.fg, border: `1px solid ${t.bd}`,
        borderRadius: 999, padding: size === "xs" ? "1px 6px" : "3px 8px",
        fontSize: fs, fontWeight: 500, lineHeight: 1.2, whiteSpace: "nowrap", ...style,
      }}
    >
      {icon ? <Icon name={icon} size={fs} stroke={1.8} /> : null}
      {children}
    </span>
  );
}

export function IconButton({
  icon, label, onClick, active, size = 28, disabled,
}: {
  icon: string; label: string; onClick?: () => void; active?: boolean; size?: number; disabled?: boolean;
}) {
  return (
    <button
      onClick={onClick}
      title={label}
      aria-label={label}
      disabled={disabled}
      style={{
        width: size, height: size, display: "inline-flex", alignItems: "center",
        justifyContent: "center", background: active ? "var(--bg-3)" : "transparent",
        color: active ? "var(--fg)" : "var(--fg-3)", border: "1px solid transparent",
        borderRadius: 6, padding: 0, opacity: disabled ? 0.4 : 1,
        transition: "background 120ms, color 120ms",
      }}
    >
      <Icon name={icon} size={size === 28 ? 16 : 18} />
    </button>
  );
}

const DOC_COLORS: Record<string, [string, string]> = {
  PDF: ["oklch(0.96 0.04 27)", "oklch(0.50 0.16 27)"],
  PPTX: ["oklch(0.96 0.04 50)", "oklch(0.50 0.15 50)"],
  DOCX: ["oklch(0.96 0.04 42)", "oklch(0.52 0.15 33)"],
  XLSX: ["oklch(0.96 0.04 155)", "oklch(0.42 0.13 155)"],
  Confluence: ["oklch(0.96 0.04 42)", "oklch(0.52 0.15 33)"],
  MD: ["oklch(0.95 0.015 70)", "oklch(0.45 0.02 60)"],
  TXT: ["oklch(0.95 0.015 70)", "oklch(0.45 0.02 60)"],
  SQL: ["oklch(0.95 0.04 230)", "oklch(0.48 0.13 250)"],
  PY: ["oklch(0.95 0.05 90)", "oklch(0.48 0.12 95)"],
  JSON: ["oklch(0.95 0.04 155)", "oklch(0.42 0.13 155)"],
  XML: ["oklch(0.95 0.04 320)", "oklch(0.50 0.15 330)"],
  YAML: ["oklch(0.95 0.04 195)", "oklch(0.48 0.12 200)"],
  JS: ["oklch(0.96 0.06 95)", "oklch(0.50 0.13 90)"],
  TS: ["oklch(0.95 0.04 250)", "oklch(0.48 0.13 255)"],
  SH: ["oklch(0.94 0.02 150)", "oklch(0.42 0.06 150)"],
};

export function DocTypeBadge({ type }: { type: string }) {
  const [bg, fg] = DOC_COLORS[type] ?? ["var(--bg-3)", "var(--fg-2)"];
  return (
    <span
      className="mono"
      style={{
        display: "inline-flex", alignItems: "center", justifyContent: "center",
        minWidth: 38, height: 18, padding: "0 6px", background: bg, color: fg,
        borderRadius: 4, fontSize: 10, fontWeight: 600, letterSpacing: "0.04em", textTransform: "uppercase",
      }}
    >
      {type}
    </span>
  );
}

export function GradientAvatar({ initials, size = 30 }: { initials: string; size?: number }) {
  return (
    <div
      style={{
        width: size, height: size, borderRadius: "50%",
        background: "linear-gradient(135deg, oklch(0.66 0.16 42), oklch(0.58 0.16 35))",
        color: "white", display: "inline-flex", alignItems: "center", justifyContent: "center",
        fontSize: size * 0.4, fontWeight: 600, letterSpacing: "0.02em", flexShrink: 0,
      }}
    >
      {initials}
    </div>
  );
}

export const TONE_MAP: Record<string, { fg: string; bg: string; bd: string }> = {
  accent: { fg: "oklch(0.58 0.15 35)", bg: "oklch(0.95 0.04 42)", bd: "oklch(0.86 0.06 42)" },
  violet: { fg: "oklch(0.62 0.17 36)", bg: "oklch(0.95 0.04 36)", bd: "oklch(0.86 0.06 36)" },
  teal: { fg: "oklch(0.50 0.12 195)", bg: "oklch(0.95 0.04 195)", bd: "oklch(0.86 0.06 195)" },
  amber: { fg: "oklch(0.50 0.13 70)", bg: "oklch(0.96 0.05 80)", bd: "oklch(0.87 0.07 75)" },
  rose: { fg: "oklch(0.55 0.16 15)", bg: "oklch(0.96 0.04 15)", bd: "oklch(0.87 0.06 15)" },
  green: { fg: "oklch(0.50 0.13 155)", bg: "oklch(0.96 0.04 155)", bd: "oklch(0.86 0.06 155)" },
};

/** Map a domain to a nav tone for color rails / tags. */
export function domainTone(domain?: string | null): string {
  switch (domain) {
    case "customer": return "accent";
    case "product": return "violet";
    case "vendor":
    case "supplier": return "teal";
    case "finance": return "violet";
    case "healthcare": return "rose";
    default: return "green";
  }
}
