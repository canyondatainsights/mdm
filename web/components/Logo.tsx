"use client";

// Sidecar brand — ported from design_handoff_mdm_knowledge_base 2/brand/sidecar/logos.jsx
// V1 "Default" bubble mark + Manrope wordmark (dotless-i with a coral accent dot) + slogan.

/** Speech-bubble host (dark) + small companion "sidecar" bubble (coral). */
export function SidecarMark({ size = 26 }: { size?: number }) {
  const h = size;
  const w = size * (140 / 92);
  return (
    <svg width={w} height={h} viewBox="0 0 140 92" fill="none" role="img" aria-label="Sidecar" style={{ display: "block", flexShrink: 0 }}>
      <path
        d="M 16 4 L 64 4 Q 78 4 78 18 L 78 50 Q 78 64 64 64 L 34 64 L 22 84 L 26 64 L 16 64 Q 2 64 2 50 L 2 18 Q 2 4 16 4 Z"
        fill="var(--fg)"
      />
      <rect x="90" y="24" width="48" height="42" rx="14" fill="var(--accent)" />
    </svg>
  );
}

/** "Sidecar" — Manrope, dotless-i carrying a coral dot. */
export function SidecarWordmark({ size = 21, color = "var(--fg)", accent = "var(--accent)" }: { size?: number; color?: string; accent?: string }) {
  const dot = size * 0.26;
  return (
    <span style={{ fontFamily: "var(--font-manrope), sans-serif", fontWeight: 700, fontSize: size, color, letterSpacing: "-0.045em", lineHeight: 1, display: "inline-flex", alignItems: "baseline", whiteSpace: "nowrap" }}>
      <span>S</span>
      <span style={{ position: "relative", display: "inline-block" }}>
        {"ı"}
        <span aria-hidden style={{ position: "absolute", top: "-0.08em", left: "50%", transform: "translateX(-50%)", width: dot, height: dot, borderRadius: "50%", background: accent }} />
      </span>
      <span>decar</span>
    </span>
  );
}

/** "fetches what you need." */
export function SidecarSlogan({ size = 11.5, color = "var(--fg-3)" }: { size?: number; color?: string }) {
  return (
    <span style={{ fontFamily: "var(--font-manrope), sans-serif", fontWeight: 500, fontSize: size, letterSpacing: "0.005em", color, lineHeight: 1.2 }}>
      fetches what you need.
    </span>
  );
}
