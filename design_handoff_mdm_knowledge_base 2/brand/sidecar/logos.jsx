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

// V1 — Default: rounded rect host with tail bottom-left + small rect companion
const BubbleDefault = ({ size = 140, color, accent, palette = DEFAULT_P }) => {
  const c = color || palette.ink;
  const a = accent || palette.accent;
  return (
    <svg width={size} height={size * (92 / 140)} viewBox="0 0 140 92" fill="none" aria-label="Sidecar mark">
      <path d="
        M 16 4 L 64 4 Q 78 4 78 18 L 78 50 Q 78 64 64 64
        L 34 64 L 22 84 L 26 64 L 16 64 Q 2 64 2 50 L 2 18 Q 2 4 16 4 Z
      " fill={c}/>
      <rect x="90" y="24" width="48" height="42" rx="14" fill={a}/>
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

const BUBBLE_VARIANTS = [
  { id: 'default',   name: 'V1 · Default',   blurb: 'Rounded rect, tail-left, companion right',  Comp: BubbleDefault },
  { id: 'round',     name: 'V2 · Round',     blurb: 'Two discs in conversation',                Comp: BubbleRound },
  { id: 'stacked',   name: 'V3 · Stacked',   blurb: 'Small reply above larger response',        Comp: BubbleStacked },
  { id: 'conjoined', name: 'V4 · Conjoined', blurb: 'Two forms sharing an edge, no tail',       Comp: BubbleConjoined },
  { id: 'comic',     name: 'V5 · Comic',     blurb: 'Elliptical bubbles, classic comic feel',   Comp: BubbleComic },
];

// =====================================================
// SLOGAN
// =====================================================
const Slogan = ({ size = 15, color, palette = DEFAULT_P, italic = false, family = 'Manrope', tracking = '0.005em', uppercase = false, variant = 'fetch', weight = 500 }) => {
  const text = variant === 'fetch'
    ? (uppercase ? 'Fetches what you need' : 'fetches what you need.')
    : (uppercase ? 'Along for the ride' : 'along for the ride.');
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
  BubbleDefault, BubbleRound, BubbleStacked, BubbleConjoined, BubbleComic,
  BUBBLE_VARIANTS,
});
