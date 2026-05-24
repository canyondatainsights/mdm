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
// SidecarMark — bubble logo (chat-bubble + companion bubble)
// ============================================================
const SidecarMark = ({ size = 24, color = 'var(--fg)', accent = 'var(--accent)', style }) => (
  <svg width={size} height={size * (92 / 140)} viewBox="0 0 140 92" fill="none" style={style} aria-label="Sidecar">
    <path d="
      M 16 4 L 64 4 Q 78 4 78 18 L 78 50 Q 78 64 64 64
      L 34 64 L 22 84 L 26 64 L 16 64 Q 2 64 2 50 L 2 18 Q 2 4 16 4 Z
    " fill={color}/>
    <rect x="90" y="24" width="48" height="42" rx="14" fill={accent}/>
  </svg>
);

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

Object.assign(window, { Icon, Pill, IconButton, Avatar, DocTypeBadge, SidecarMark, SidecarWordmark });
