// Sidecar — Bubble direction · type / mark / color explorations

// =====================================================
// PALETTE SYSTEMS
// =====================================================
const PALETTES = {
  coral: {
    name: 'Coral & Bone',
    blurb: 'Warm, friendly, vintage motorcycle DNA',
    bg:        'oklch(0.97 0.012 80)',
    bg2:       'oklch(0.94 0.015 80)',
    cream:     'oklch(0.985 0.010 80)',
    ink:       'oklch(0.20 0.018 50)',
    inkSoft:   'oklch(0.35 0.020 50)',
    inkMute:   'oklch(0.55 0.015 50)',
    inkFaint:  'oklch(0.82 0.012 80)',
    accent:    'oklch(0.65 0.17 35)',
    accentDeep:'oklch(0.55 0.18 30)',
    dark:      'oklch(0.22 0.020 50)',
  },
  indigo: {
    name: 'Indigo & Cloud',
    blurb: 'Calm, trustworthy, classic-tech',
    bg:        'oklch(0.98 0.005 255)',
    bg2:       'oklch(0.955 0.010 255)',
    cream:     'oklch(0.99 0.004 255)',
    ink:       'oklch(0.20 0.025 265)',
    inkSoft:   'oklch(0.35 0.022 265)',
    inkMute:   'oklch(0.55 0.018 265)',
    inkFaint:  'oklch(0.85 0.012 255)',
    accent:    'oklch(0.55 0.18 270)',
    accentDeep:'oklch(0.45 0.20 270)',
    dark:      'oklch(0.20 0.040 265)',
  },
  forest: {
    name: 'Forest & Linen',
    blurb: 'Organic, grounded, calm',
    bg:        'oklch(0.97 0.010 120)',
    bg2:       'oklch(0.95 0.013 120)',
    cream:     'oklch(0.985 0.008 120)',
    ink:       'oklch(0.22 0.025 155)',
    inkSoft:   'oklch(0.36 0.022 155)',
    inkMute:   'oklch(0.55 0.018 155)',
    inkFaint:  'oklch(0.84 0.014 120)',
    accent:    'oklch(0.52 0.14 155)',
    accentDeep:'oklch(0.42 0.15 155)',
    dark:      'oklch(0.22 0.030 155)',
  },
  plum: {
    name: 'Plum & Pearl',
    blurb: 'Sophisticated, considered, premium',
    bg:        'oklch(0.97 0.008 330)',
    bg2:       'oklch(0.95 0.012 330)',
    cream:     'oklch(0.985 0.006 330)',
    ink:       'oklch(0.22 0.030 340)',
    inkSoft:   'oklch(0.36 0.025 340)',
    inkMute:   'oklch(0.55 0.020 340)',
    inkFaint:  'oklch(0.85 0.012 330)',
    accent:    'oklch(0.52 0.17 340)',
    accentDeep:'oklch(0.42 0.18 340)',
    dark:      'oklch(0.22 0.035 340)',
  },
  cobalt: {
    name: 'Cobalt & Mist',
    blurb: 'Electric, confident, energetic',
    bg:        'oklch(0.98 0.005 240)',
    bg2:       'oklch(0.95 0.010 240)',
    cream:     'oklch(0.99 0.004 240)',
    ink:       'oklch(0.20 0.030 250)',
    inkSoft:   'oklch(0.35 0.025 250)',
    inkMute:   'oklch(0.55 0.020 250)',
    inkFaint:  'oklch(0.85 0.014 240)',
    accent:    'oklch(0.55 0.20 250)',
    accentDeep:'oklch(0.45 0.22 250)',
    dark:      'oklch(0.20 0.035 250)',
  },
};

const DEFAULT_P = PALETTES.coral;
const PAL = DEFAULT_P; // back-compat alias

// =====================================================
// TYPEFACES
// =====================================================
const TYPEFACES = [
  { id: 'manrope',    family: 'Manrope',            weight: 700, tracking: '-0.045em', blurb: 'Geometric, friendly, soft terminals — current pick',      offset: '-0.08em', dotScale: 0.26 },
  { id: 'inter-tight',family: 'Inter Tight',        weight: 700, tracking: '-0.035em', blurb: 'Modern workhorse, neutral, very legible',                  offset: '-0.06em', dotScale: 0.24 },
  { id: 'dm-sans',    family: 'DM Sans',            weight: 700, tracking: '-0.035em', blurb: 'Geometric, square-ish, low-key Googly',                    offset: '-0.06em', dotScale: 0.24 },
  { id: 'hanken',     family: 'Hanken Grotesk',     weight: 700, tracking: '-0.030em', blurb: 'Solid, utilitarian, Swiss flavor',                         offset: '-0.05em', dotScale: 0.22 },
  { id: 'outfit',     family: 'Outfit',             weight: 700, tracking: '-0.035em', blurb: 'Slightly playful, rounder, friendly',                      offset: '-0.07em', dotScale: 0.25 },
  { id: 'bricolage',  family: 'Bricolage Grotesque',weight: 700, tracking: '-0.040em', blurb: 'Characterful, slightly literary',                          offset: '-0.05em', dotScale: 0.23 },
];

const TF = id => TYPEFACES.find(t => t.id === id) || TYPEFACES[0];

// =====================================================
// WORDMARK
// =====================================================
// dotless-i with our own coral dot positioned above
const Wordmark = ({ size = 60, color, accent, palette = DEFAULT_P, tf = 'manrope', plain = false }) => {
  const T = TF(tf);
  const c = color || palette.ink;
  const a = accent || palette.accent;
  return (
    <span style={{
      fontFamily: `'${T.family}', sans-serif`,
      fontWeight: T.weight,
      fontSize: size,
      color: c,
      letterSpacing: T.tracking,
      lineHeight: 1,
      display: 'inline-flex',
      alignItems: 'baseline',
      whiteSpace: 'nowrap',
    }}>
      <span>S</span>
      <span style={{ position: 'relative', display: 'inline-block' }}>
        {plain ? 'i' : (
          <>
            {'\u0131'}
            <span aria-hidden="true" style={{
              position: 'absolute',
              top: T.offset,
              left: '50%',
              transform: 'translateX(-50%)',
              width: `${T.dotScale}em`,
              height: `${T.dotScale}em`,
              borderRadius: '50%',
              background: a,
            }}/>
          </>
        )}
      </span>
      <span>decar</span>
    </span>
  );
};

// =====================================================
// BUBBLE MARK VARIATIONS
// =====================================================

// V1 — Default: large coral bubble (tail-left) + small ink buddy (tail-left) with two dot eyes
// (Reflects the "big bubble + little buddy bubble" reference)
const BubbleDefault = ({ size = 140, color, accent, palette = DEFAULT_P, faceless = false }) => {
  const c = color || palette.ink;     // small buddy
  const a = accent || palette.accent; // big bubble
  return (
    <svg width={size} height={size * (110 / 140)} viewBox="0 0 140 110" fill="none" aria-label="Sidecar mark">
      {/* Big coral bubble */}
      <path d="
        M 16 6 L 70 6 Q 84 6 84 20 L 84 64 Q 84 78 70 78
        L 36 78 L 22 100 L 28 78 L 16 78 Q 2 78 2 64 L 2 20 Q 2 6 16 6 Z
      " fill={a}/>
      {/* Small ink buddy */}
      <path d="
        M 102 30 L 128 30 Q 138 30 138 40 L 138 64 Q 138 74 128 74
        L 118 74 L 112 90 L 116 74 L 102 74 Q 92 74 92 64 L 92 40 Q 92 30 102 30 Z
      " fill={c}/>
      {!faceless && (
        <>
          {/* Two dot eyes on the buddy */}
          <circle cx="108" cy="52" r="3" fill={palette.bg}/>
          <circle cx="122" cy="52" r="3" fill={palette.bg}/>
        </>
      )}
    </svg>
  );
};

// V2 — Round: two discs in conversation, tiny tail on the big one
const BubbleRound = ({ size = 140, color, accent, palette = DEFAULT_P }) => {
  const c = color || palette.ink;
  const a = accent || palette.accent;
  return (
    <svg width={size} height={size * (92 / 140)} viewBox="0 0 140 92" fill="none" aria-label="Sidecar mark">
      <circle cx="42" cy="42" r="38" fill={c}/>
      {/* tail */}
      <path d="M 20 68 L 14 88 L 32 74 Z" fill={c}/>
      <circle cx="112" cy="56" r="22" fill={a}/>
    </svg>
  );
};

// V3 — Stacked: small bubble above-left, larger bubble below-right (a thread)
const BubbleStacked = ({ size = 110, color, accent, palette = DEFAULT_P }) => {
  const c = color || palette.ink;
  const a = accent || palette.accent;
  return (
    <svg width={size} height={size * (120 / 110)} viewBox="0 0 110 120" fill="none" aria-label="Sidecar mark">
      {/* small (top) */}
      <rect x="6" y="6" width="50" height="34" rx="12" fill={a}/>
      <path d="M 14 36 L 10 48 L 22 38 Z" fill={a}/>
      {/* big (bottom) */}
      <rect x="34" y="54" width="72" height="58" rx="18" fill={c}/>
      <path d="M 96 110 L 102 122 L 86 110 Z" fill={c}/>
    </svg>
  );
};

// V4 — Conjoined: two rounded forms sharing an edge, no tail, most graphic
const BubbleConjoined = ({ size = 140, color, accent, palette = DEFAULT_P }) => {
  const c = color || palette.ink;
  const a = accent || palette.accent;
  return (
    <svg width={size} height={size * (84 / 140)} viewBox="0 0 140 84" fill="none" aria-label="Sidecar mark">
      <rect x="2" y="6" width="86" height="72" rx="22" fill={c}/>
      <rect x="78" y="24" width="60" height="48" rx="18" fill={a}/>
    </svg>
  );
};

// V5 — Comic: classic comic-book speech bubble (rounder, prominent tail) + companion
const BubbleComic = ({ size = 140, color, accent, palette = DEFAULT_P }) => {
  const c = color || palette.ink;
  const a = accent || palette.accent;
  return (
    <svg width={size} height={size * (96 / 140)} viewBox="0 0 140 96" fill="none" aria-label="Sidecar mark">
      <ellipse cx="44" cy="38" rx="40" ry="32" fill={c}/>
      <path d="M 36 64 L 26 92 L 56 68 Z" fill={c}/>
      <ellipse cx="116" cy="56" rx="22" ry="18" fill={a}/>
    </svg>
  );
};

// V6 — Friendly Face: single coral bubble with eyes + smile inside
// (Reflects the "bubble with a friendly face" reference)
const BubbleFace = ({ size = 140, color, accent, palette = DEFAULT_P }) => {
  // accent = bubble fill, color = facial features
  const bubble = accent || palette.accent;
  const feature = color || palette.ink;
  return (
    <svg width={size} height={size * (130 / 140)} viewBox="0 0 140 130" fill="none" aria-label="Sidecar">
      {/* Bubble */}
      <path d="
        M 18 6 L 122 6 Q 138 6 138 22 L 138 78 Q 138 94 122 94
        L 78 94 L 58 124 L 64 94 L 18 94 Q 2 94 2 78 L 2 22 Q 2 6 18 6 Z
      " fill={bubble}/>
      {/* Eyes — circular pupils inside white-of-eye discs for life */}
      <circle cx="52" cy="42" r="9" fill={palette.bg}/>
      <circle cx="88" cy="42" r="9" fill={palette.bg}/>
      <circle cx="55" cy="44" r="4.5" fill={feature}/>
      <circle cx="91" cy="44" r="4.5" fill={feature}/>
      {/* Smile */}
      <path d="M 50 64 Q 70 80 90 64" stroke={feature} strokeWidth="5" strokeLinecap="round" fill="none"/>
    </svg>
  );
};

// Compact version for product avatars / favicons (no tail, square-ish)
const BubbleFaceCompact = ({ size = 32, color, accent, palette = DEFAULT_P }) => {
  const bubble = accent || palette.accent;
  const feature = color || palette.ink;
  return (
    <svg width={size} height={size} viewBox="0 0 100 100" fill="none" aria-label="Sidecar">
      <path d="
        M 14 8 L 86 8 Q 96 8 96 18 L 96 66 Q 96 76 86 76
        L 52 76 L 38 94 L 42 76 L 14 76 Q 4 76 4 66 L 4 18 Q 4 8 14 8 Z
      " fill={bubble}/>
      <circle cx="36" cy="36" r="6" fill={feature}/>
      <circle cx="64" cy="36" r="6" fill={feature}/>
      <path d="M 34 54 Q 50 66 66 54" stroke={feature} strokeWidth="4.5" strokeLinecap="round" fill="none"/>
    </svg>
  );
};

const BUBBLE_VARIANTS = [
  { id: 'face',      name: 'V0 · Friendly face',  blurb: 'Single bubble with eyes & smile — character',  Comp: BubbleFace },
  { id: 'default',   name: 'V1 · Buddy duo',      blurb: 'Big bubble + little buddy with dot eyes',      Comp: BubbleDefault },
  { id: 'round',     name: 'V2 · Round',          blurb: 'Two discs in conversation',                    Comp: BubbleRound },
  { id: 'stacked',   name: 'V3 · Stacked',        blurb: 'Small reply above larger response',            Comp: BubbleStacked },
  { id: 'conjoined', name: 'V4 · Conjoined',      blurb: 'Two forms sharing an edge, no tail',           Comp: BubbleConjoined },
  { id: 'comic',     name: 'V5 · Comic',          blurb: 'Elliptical bubbles, classic comic feel',       Comp: BubbleComic },
];

// =====================================================
// SLOGAN
// =====================================================
const Slogan = ({ size = 15, color, palette = DEFAULT_P, italic = false, family = 'Manrope', tracking = '0.005em', uppercase = false, variant = 'fetch', weight = 500 }) => {
  let text;
  if (variant === 'fetch') text = uppercase ? 'Fetches what you need' : 'fetches what you need.';
  else if (variant === 'side') text = uppercase ? 'Always by your side' : 'always by your side.';
  else text = uppercase ? 'Along for the ride' : 'along for the ride.';
  return (
    <span style={{
      fontFamily: `'${family}', sans-serif`,
      fontStyle: italic ? 'italic' : 'normal',
      fontWeight: weight,
      fontSize: size,
      letterSpacing: uppercase ? '0.18em' : tracking,
      textTransform: uppercase ? 'uppercase' : 'none',
      color: color || palette.inkSoft,
      lineHeight: 1.2,
      display: 'inline-block',
    }}>{text}</span>
  );
};

// =====================================================
// HELPERS
// =====================================================
const Frame = ({ children, bg, palette = DEFAULT_P, pad = 32, style }) => (
  <div style={{
    width: '100%', height: '100%',
    background: bg || palette.bg,
    padding: pad,
    display: 'flex',
    flexDirection: 'column',
    fontFamily: "'Manrope', sans-serif",
    position: 'relative',
    overflow: 'hidden',
    ...style,
  }}>{children}</div>
);

const TagChip = ({ children, color, bg = 'transparent', border, palette = DEFAULT_P, style }) => (
  <span style={{
    fontFamily: "'JetBrains Mono', ui-monospace, monospace",
    fontSize: 10,
    letterSpacing: '0.10em',
    textTransform: 'uppercase',
    color: color || palette.inkMute,
    background: bg,
    border: `1px solid ${border || palette.inkFaint}`,
    padding: '3px 7px', borderRadius: 999,
    display: 'inline-block',
    ...style,
  }}>{children}</span>
);

Object.assign(window, {
  PAL, PALETTES, TYPEFACES, TF,
  Wordmark, Slogan, Frame, TagChip,
  BubbleDefault, BubbleRound, BubbleStacked, BubbleConjoined, BubbleComic, BubbleFace, BubbleFaceCompact,
  BUBBLE_VARIANTS,
});
