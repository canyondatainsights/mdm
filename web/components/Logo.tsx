"use client";

// Sidecar brand — ported from design_handoff_mdm_knowledge_base 2/brand/sidecar/logos.jsx
// V1 "Default" bubble mark + Manrope wordmark (dotless-i with a coral accent dot) + slogan.

/** "Buddy Duo" — big coral host bubble + small dark companion "sidecar" bubble with dot eyes. */
export function SidecarMark({ size = 26 }: { size?: number }) {
  const h = size;
  const w = size * (140 / 110);
  return (
    <svg width={w} height={h} viewBox="0 0 140 110" fill="none" role="img" aria-label="Sidecar" style={{ display: "block", flexShrink: 0 }}>
      <path
        d="M 16 6 L 70 6 Q 84 6 84 20 L 84 64 Q 84 78 70 78 L 36 78 L 22 100 L 28 78 L 16 78 Q 2 78 2 64 L 2 20 Q 2 6 16 6 Z"
        fill="var(--accent)"
      />
      <path
        d="M 102 30 L 128 30 Q 138 30 138 40 L 138 64 Q 138 74 128 74 L 118 74 L 112 90 L 116 74 L 102 74 Q 92 74 92 64 L 92 40 Q 92 30 102 30 Z"
        fill="var(--fg)"
      />
      <circle cx="108" cy="52" r="3.2" fill="rgba(255,255,255,0.92)" />
      <circle cx="122" cy="52" r="3.2" fill="rgba(255,255,255,0.92)" />
    </svg>
  );
}

/**
 * "Friendly Face" — single coral bubble with eyes + smile. The assistant's avatar.
 * - `thinking` (while the assistant streams): the smile becomes 3 bouncing typing-dots, the eyes
 *   blink faster, and the bubble gently bobs.
 * - `idle`: a slow occasional blink (gives the focal landing face some life).
 * All motion is CSS (see globals.css .sc-face*) and disabled under prefers-reduced-motion.
 */
export function SidecarFace({ size = 28, thinking = false, idle = false }: { size?: number; thinking?: boolean; idle?: boolean }) {
  const cls = ["sc-face", thinking ? "sc-face--thinking" : idle ? "sc-face--idle" : ""].filter(Boolean).join(" ");
  return (
    <svg width={size} height={size} viewBox="0 0 100 100" fill="none" role="img" aria-label="Sidecar" className={cls} style={{ display: "block", flexShrink: 0 }}>
      <path
        d="M 14 8 L 86 8 Q 96 8 96 18 L 96 66 Q 96 76 86 76 L 52 76 L 38 94 L 42 76 L 14 76 Q 4 76 4 66 L 4 18 Q 4 8 14 8 Z"
        fill="var(--accent)"
      />
      <g className="sc-face__eyes">
        <circle cx="36" cy="36" r="6.5" fill="var(--bg)" />
        <circle cx="64" cy="36" r="6.5" fill="var(--bg)" />
        <circle cx="38" cy="38" r="3.2" fill="var(--fg)" />
        <circle cx="66" cy="38" r="3.2" fill="var(--fg)" />
      </g>
      {thinking ? (
        <g className="sc-face__dots" fill="var(--fg)" aria-label="thinking">
          <circle cx="34" cy="56" r="3.6" />
          <circle cx="50" cy="56" r="3.6" />
          <circle cx="66" cy="56" r="3.6" />
        </g>
      ) : (
        <path d="M 34 54 Q 50 66 66 54" stroke="var(--fg)" strokeWidth="4.5" strokeLinecap="round" fill="none" />
      )}
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
