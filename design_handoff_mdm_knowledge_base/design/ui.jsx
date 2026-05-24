// Reusable UI primitives + icons for MDM Knowledge Base

const Icon = ({ name, size = 16, stroke = 1.6, className = '', style }) => {
  const s = { width: size, height: size, ...style };
  const common = { width: size, height: size, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: stroke, strokeLinecap: 'round', strokeLinejoin: 'round', className, style: s };
  switch (name) {
    case 'search': return <svg {...common}><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>;
    case 'plus': return <svg {...common}><path d="M12 5v14M5 12h14"/></svg>;
    case 'send': return <svg {...common}><path d="M5 12h14M13 6l6 6-6 6"/></svg>;
    case 'paperclip': return <svg {...common}><path d="m21 12-8.5 8.5a5 5 0 1 1-7-7l9-9a3.5 3.5 0 1 1 5 5L11 18a2 2 0 1 1-3-3l7-7"/></svg>;
    case 'sparkle': return <svg {...common}><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/></svg>;
    case 'book': return <svg {...common}><path d="M4 4h11a4 4 0 0 1 4 4v12H8a4 4 0 0 1-4-4V4Z"/><path d="M4 16a4 4 0 0 1 4-4h11"/></svg>;
    case 'doc': return <svg {...common}><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>;
    case 'pin': return <svg {...common}><path d="M12 17v5M9 3h6l-1 6 3 3H7l3-3z"/></svg>;
    case 'panel': return <svg {...common}><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M15 4v16"/></svg>;
    case 'sidebar': return <svg {...common}><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>;
    case 'chevron-down': return <svg {...common}><path d="m6 9 6 6 6-6"/></svg>;
    case 'chevron-right': return <svg {...common}><path d="m9 6 6 6-6 6"/></svg>;
    case 'check': return <svg {...common}><path d="m5 12 5 5L20 7"/></svg>;
    case 'copy': return <svg {...common}><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>;
    case 'thumbsup': return <svg {...common}><path d="M7 10v11M3 14v5a2 2 0 0 0 2 2h11.3a3 3 0 0 0 3-2.4l1.4-7A2 2 0 0 0 18.7 10H14V5a3 3 0 0 0-3-3l-4 8"/></svg>;
    case 'thumbsdown': return <svg {...common}><path d="M17 14V3M21 10V5a2 2 0 0 0-2-2H7.7a3 3 0 0 0-3 2.4l-1.4 7A2 2 0 0 0 5.3 14H10v5a3 3 0 0 0 3 3l4-8"/></svg>;
    case 'refresh': return <svg {...common}><path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5"/></svg>;
    case 'filter': return <svg {...common}><path d="M3 5h18M6 12h12M10 19h4"/></svg>;
    case 'tag': return <svg {...common}><path d="M12 2H4v8l10 10 8-8z"/><circle cx="8" cy="8" r="1.5"/></svg>;
    case 'shield': return <svg {...common}><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/></svg>;
    case 'link': return <svg {...common}><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>;
    case 'external': return <svg {...common}><path d="M14 4h6v6M20 4 10 14M19 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h6"/></svg>;
    case 'spark-mini': return <svg {...common}><path d="M12 4v3M12 17v3M4 12h3M17 12h3M7 7l2 2M15 15l2 2M7 17l2-2M15 9l2-2"/></svg>;
    case 'dot': return <svg {...common}><circle cx="12" cy="12" r="4"/></svg>;
    case 'database': return <svg {...common}><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>;
    case 'graph': return <svg {...common}><circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M7.7 7.8 10.3 16.2M16.3 7.8 13.7 16.2M8.5 6h7"/></svg>;
    case 'history': return <svg {...common}><path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>;
    case 'more': return <svg {...common}><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>;
    case 'arrow-up': return <svg {...common}><path d="M12 19V5M5 12l7-7 7 7"/></svg>;
    case 'flow': return <svg {...common}><rect x="3" y="4" width="7" height="6" rx="1"/><rect x="14" y="14" width="7" height="6" rx="1"/><path d="M10 7h2a2 2 0 0 1 2 2v8"/></svg>;
    case 'globe': return <svg {...common}><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>;
    default: return null;
  }
};

const Pill = ({ children, tone = 'neutral', icon, size = 'sm', style }) => {
  const tones = {
    neutral: { bg: 'var(--bg-3)', fg: 'var(--fg-2)', bd: 'var(--border)' },
    accent:  { bg: 'var(--accent-soft)', fg: 'var(--accent-2)', bd: 'var(--accent-border)' },
    ok:      { bg: 'oklch(0.96 0.04 155)', fg: 'oklch(0.42 0.13 155)', bd: 'oklch(0.86 0.06 155)' },
    warn:    { bg: 'oklch(0.97 0.04 80)',  fg: 'oklch(0.48 0.12 70)',  bd: 'oklch(0.88 0.06 80)' },
  };
  const t = tones[tone] || tones.neutral;
  const pad = size === 'xs' ? '1px 6px' : '3px 8px';
  const fs = size === 'xs' ? 11 : 12;
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 4,
      background: t.bg, color: t.fg, border: `1px solid ${t.bd}`,
      borderRadius: 999, padding: pad, fontSize: fs, fontWeight: 500, lineHeight: 1.2,
      whiteSpace: 'nowrap',
      ...style
    }}>
      {icon ? <Icon name={icon} size={fs} stroke={1.8}/> : null}
      {children}
    </span>
  );
};

const IconButton = ({ icon, label, onClick, active, size = 28, style }) => (
  <button
    onClick={onClick}
    title={label}
    aria-label={label}
    style={{
      width: size, height: size,
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      background: active ? 'var(--bg-3)' : 'transparent',
      color: active ? 'var(--fg)' : 'var(--fg-3)',
      border: '1px solid transparent',
      borderRadius: 6, padding: 0,
      transition: 'background 120ms, color 120ms, border-color 120ms',
      ...style
    }}
    onMouseEnter={e => { if (!active) { e.currentTarget.style.background = 'var(--bg-3)'; e.currentTarget.style.color = 'var(--fg-2)'; } }}
    onMouseLeave={e => { if (!active) { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = 'var(--fg-3)'; } }}
  >
    <Icon name={icon} size={size === 28 ? 16 : 18}/>
  </button>
);

const Avatar = ({ name = 'You', tone = 'neutral', size = 28 }) => {
  const initials = name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
  const bg = tone === 'accent' ? 'var(--accent)' : 'oklch(0.30 0.02 250)';
  return (
    <div style={{
      width: size, height: size, borderRadius: '50%',
      background: bg, color: 'white',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      fontSize: size * 0.4, fontWeight: 600, letterSpacing: '0.02em',
      flexShrink: 0
    }}>{initials}</div>
  );
};

// ============================================================
// Sidecar marks — two variants
// ============================================================

// Concept 1 — Buddy Duo: primary product logo
const SidecarMarkBuddy = ({ size = 28, big = '#D97757', small = '#1F1B17', dotColor, style }) => {
  const eyeColor = dotColor || 'rgba(255,255,255,0.92)';
  return (
    <svg width={size} height={size * (110 / 140)} viewBox="0 0 140 110" fill="none" style={style} aria-label="Sidecar">
      <path d="M 16 6 L 70 6 Q 84 6 84 20 L 84 64 Q 84 78 70 78 L 36 78 L 22 100 L 28 78 L 16 78 Q 2 78 2 64 L 2 20 Q 2 6 16 6 Z" fill={big}/>
      <path d="M 102 30 L 128 30 Q 138 30 138 40 L 138 64 Q 138 74 128 74 L 118 74 L 112 90 L 116 74 L 102 74 Q 92 74 92 64 L 92 40 Q 92 30 102 30 Z" fill={small}/>
      <circle cx="108" cy="52" r="3.2" fill={eyeColor}/>
      <circle cx="122" cy="52" r="3.2" fill={eyeColor}/>
    </svg>
  );
};

// Concept 4 — Friendly Face: assistant avatar
const SidecarMarkFace = ({ size = 24, bubble = '#D97757', feature = '#1F1B17', whites = '#F7F2EC', style }) => (
  <svg width={size} height={size} viewBox="0 0 100 100" fill="none" style={style} aria-label="Sidecar Assistant">
    <path d="M 14 8 L 86 8 Q 96 8 96 18 L 96 66 Q 96 76 86 76 L 52 76 L 38 94 L 42 76 L 14 76 Q 4 76 4 66 L 4 18 Q 4 8 14 8 Z" fill={bubble}/>
    <circle cx="36" cy="36" r="6.5" fill={whites}/>
    <circle cx="64" cy="36" r="6.5" fill={whites}/>
    <circle cx="38" cy="38" r="3.2" fill={feature}/>
    <circle cx="66" cy="38" r="3.2" fill={feature}/>
    <path d="M 34 54 Q 50 66 66 54" stroke={feature} strokeWidth="4.5" strokeLinecap="round" fill="none"/>
  </svg>
);

// Back-compat default — points to Buddy now
const SidecarMark = SidecarMarkBuddy;

// ============================================================
// Vendor / Platform / Model color system
// ============================================================
// Each vendor / platform / model has a unique hue. Color depth
// expresses hierarchy depth:
//   Level 1 — Vendor      (boldest)
//   Level 2 — Product     (mid)
//   Level 3 — Data Domain (light)
//   Level 4 — Extension   (minimal — dot only)
// Data Platforms and Financial Models occupy distinct hue bands so
// they're recognizable as a category at a glance.

const VENDORS = {
  // ---- MDM Vendors ----
  informatica: { name: 'Informatica',   hue: 28,  c: 0.18, kind: 'vendor' }, // signature orange
  oracle:      { name: 'Oracle',        hue: 18,  c: 0.20, kind: 'vendor' }, // brand red
  sap:         { name: 'SAP',           hue: 248, c: 0.16, kind: 'vendor' }, // corporate blue
  reltio:      { name: 'Reltio',        hue: 295, c: 0.18, kind: 'vendor' }, // violet
  ibm:         { name: 'IBM',           hue: 215, c: 0.16, kind: 'vendor' }, // azure
  semarchy:    { name: 'Semarchy',      hue: 155, c: 0.14, kind: 'vendor' }, // green
  stibo:       { name: 'Stibo',         hue: 75,  c: 0.16, kind: 'vendor' }, // ochre
  // ---- Data Platforms ----
  snowflake:   { name: 'Snowflake',     hue: 200, c: 0.14, kind: 'platform' }, // ice
  databricks:  { name: 'Databricks',    hue: 12,  c: 0.18, kind: 'platform' }, // brick
  bigquery:    { name: 'BigQuery',      hue: 260, c: 0.16, kind: 'platform' }, // indigo
  redshift:    { name: 'Redshift',      hue: 5,   c: 0.20, kind: 'platform' }, // crimson
  synapse:     { name: 'Synapse',       hue: 178, c: 0.13, kind: 'platform' }, // teal
  // ---- Financial Models ----
  fibo:        { name: 'FIBO',          hue: 135, c: 0.13, kind: 'model' }, // forest
  acord:       { name: 'ACORD',         hue: 170, c: 0.13, kind: 'model' }, // moss
  ifrs:        { name: 'IFRS',          hue: 115, c: 0.13, kind: 'model' }, // sage
  basel:       { name: 'Basel III',     hue: 100, c: 0.13, kind: 'model' }, // olive
};

const KIND_LABEL = { vendor: 'VENDOR', platform: 'PLATFORM', model: 'MODEL' };

// Compute fg/bg/border tone for a given hue at a given level (1..4)
const vendorTone = (hue, c, level) => {
  // Level 1 is the strongest, level 4 is invisible (just a dot)
  const L  = { 1: 0.42, 2: 0.48, 3: 0.55, 4: 0.55 };
  const Bg = { 1: 0.93, 2: 0.96, 3: 0.98, 4: 1.00 };
  const Bd = { 1: 0.76, 2: 0.84, 3: 0.90, 4: 1.00 };
  const C  = { 1: 0.14, 2: 0.10, 3: 0.06, 4: 0.04 };
  return {
    fg: `oklch(${L[level]} ${Math.min(c, C[level])} ${hue})`,
    bg: `oklch(${Bg[level]} ${Math.min(c, C[level]) * 0.5} ${hue})`,
    bd: `oklch(${Bd[level]} ${Math.min(c, C[level]) * 0.6} ${hue})`,
    dot:`oklch(${L[level]} ${c} ${hue})`,
  };
};

// VendorPill — a single chip at any hierarchy level.
// `vendor` is the vendor/platform/model key; `level` is 1..4.
// `subtle` reduces visual weight (used when many pills appear together).
const VendorPill = ({ vendor, level = 1, children, mono = false, subtle = false, style }) => {
  const v = VENDORS[vendor];
  if (!v) return null;
  const t = vendorTone(v.hue, v.c, level);

  // Level 4 (extension) — just a tiny dot + label, no chrome
  if (level === 4) {
    return (
      <span style={{
        display: 'inline-flex', alignItems: 'center', gap: 5,
        fontSize: 10.5, color: 'var(--fg-2)',
        whiteSpace: 'nowrap',
        ...style,
      }}>
        <span style={{ width: 6, height: 6, borderRadius: '50%', background: t.dot, flexShrink: 0 }}/>
        {children}
      </span>
    );
  }

  const fontSize = level === 1 ? 10.5 : 11;
  const fontWeight = level === 1 ? 600 : 500;
  const pad = level === 1 ? '1px 6px 1px 5px' : '1px 7px';
  const letterSpacing = level === 1 && mono ? '0.08em' : '0';
  const fontFamily = level === 1 && mono ? "'JetBrains Mono', monospace" : 'inherit';
  const textTransform = level === 1 && mono ? 'uppercase' : 'none';

  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 4,
      padding: pad,
      fontSize, fontWeight,
      fontFamily, letterSpacing, textTransform,
      color: subtle ? `oklch(0.45 ${v.c * 0.6} ${v.hue})` : t.fg,
      background: subtle ? 'transparent' : t.bg,
      border: `1px solid ${subtle ? t.bg : t.bd}`,
      borderRadius: level === 1 ? 4 : 999,
      lineHeight: 1.4,
      whiteSpace: 'nowrap',
      ...style,
    }}>
      {level === 1 ? (
        <span style={{ width: 6, height: 6, borderRadius: '50%', background: t.dot, flexShrink: 0 }}/>
      ) : null}
      {children}
    </span>
  );
};

// KindBadge — labels a vendor key as VENDOR / PLATFORM / MODEL
const KindBadge = ({ kind, style }) => (
  <span style={{
    fontFamily: "'JetBrains Mono', monospace",
    fontSize: 9, fontWeight: 600,
    color: 'var(--fg-4)',
    letterSpacing: '0.10em',
    textTransform: 'uppercase',
    ...style,
  }}>{KIND_LABEL[kind] || kind}</span>
);

// HierarchyChain — full breadcrumb at increasing depth
const Caret = () => (
  <span style={{ color: 'var(--fg-4)', fontSize: 10, lineHeight: 1, fontWeight: 400, padding: '0 1px' }}>›</span>
);

const HierarchyChain = ({ vendor, product, domain, extension, dense = false, style }) => {
  if (!VENDORS[vendor]) return null;
  return (
    <div style={{
      display: 'inline-flex', alignItems: 'center', gap: dense ? 3 : 5,
      flexWrap: 'wrap',
      ...style,
    }}>
      <VendorPill vendor={vendor} level={1} mono>{VENDORS[vendor].name}</VendorPill>
      {product ? (<><Caret/><VendorPill vendor={vendor} level={2}>{product}</VendorPill></>) : null}
      {domain ? (<><Caret/><VendorPill vendor={vendor} level={3}>{domain}</VendorPill></>) : null}
      {extension ? (<><Caret/><VendorPill vendor={vendor} level={4}>{extension}</VendorPill></>) : null}
    </div>
  );
};

// ============================================================
// SidecarWordmark — Inter Tight 700 with dotless-i + accent disc
// ============================================================
const SidecarWordmark = ({ size = 14, color = 'var(--fg)', accent = 'var(--accent)', style }) => (
  <span style={{
    fontFamily: "'Inter Tight', 'Inter', sans-serif",
    fontWeight: 700,
    fontSize: size,
    color,
    letterSpacing: '-0.035em',
    lineHeight: 1,
    display: 'inline-flex',
    alignItems: 'baseline',
    whiteSpace: 'nowrap',
    ...style,
  }}>
    <span>S</span>
    <span style={{ position: 'relative', display: 'inline-block' }}>
      {'\u0131'}
      <span aria-hidden="true" style={{
        position: 'absolute',
        top: '-0.06em',
        left: '50%',
        transform: 'translateX(-50%)',
        width: '0.24em',
        height: '0.24em',
        borderRadius: '50%',
        background: accent,
      }}/>
    </span>
    <span>decar</span>
  </span>
);

const DocTypeBadge = ({ type }) => {
  const colors = {
    PDF:   ['oklch(0.96 0.04 27)',  'oklch(0.50 0.16 27)'],
    PPTX:  ['oklch(0.96 0.04 50)',  'oklch(0.50 0.15 50)'],
    DOCX:  ['oklch(0.96 0.04 252)', 'oklch(0.45 0.14 252)'],
    XLSX:  ['oklch(0.96 0.04 155)', 'oklch(0.42 0.13 155)'],
    Confluence: ['oklch(0.96 0.04 252)', 'oklch(0.45 0.14 252)'],
  };
  const [bg, fg] = colors[type] || ['var(--bg-3)', 'var(--fg-2)'];
  return (
    <span className="mono" style={{
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      minWidth: 38, height: 18, padding: '0 6px',
      background: bg, color: fg, borderRadius: 4,
      fontSize: 10, fontWeight: 600, letterSpacing: '0.04em', textTransform: 'uppercase'
    }}>{type}</span>
  );
};

Object.assign(window, { Icon, Pill, IconButton, Avatar, DocTypeBadge, SidecarMark, SidecarMarkBuddy, SidecarMarkFace, SidecarWordmark, VENDORS, vendorTone, VendorPill, HierarchyChain, KindBadge, Caret });
