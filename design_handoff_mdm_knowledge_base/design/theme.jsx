// Theme switcher — small floating chip in bottom-right
// Swaps CSS custom properties on :root to recolor the whole product.

const THEMES = {
  cool: {
    name: 'Cool',
    blurb: 'Default — calm cool grays + deep blue accent',
    swatches: ['oklch(0.50 0.14 252)', 'oklch(0.20 0.018 250)', 'oklch(0.97 0.005 250)'],
    vars: {
      '--bg':            'oklch(0.992 0.003 250)',
      '--bg-2':          'oklch(0.975 0.004 250)',
      '--bg-3':          'oklch(0.955 0.005 250)',
      '--border':        'oklch(0.918 0.006 250)',
      '--border-strong': 'oklch(0.86 0.008 250)',
      '--fg':            'oklch(0.18 0.012 250)',
      '--fg-2':          'oklch(0.36 0.012 250)',
      '--fg-3':          'oklch(0.55 0.012 250)',
      '--fg-4':          'oklch(0.70 0.010 250)',
      '--accent':        'oklch(0.50 0.14 252)',
      '--accent-2':      'oklch(0.42 0.15 252)',
      '--accent-soft':   'oklch(0.96 0.025 252)',
      '--accent-border': 'oklch(0.88 0.05 252)',
    },
  },
  paper: {
    name: 'Paper',
    blurb: 'Warm off-white — soft, document-like',
    swatches: ['oklch(0.55 0.15 60)', 'oklch(0.22 0.015 60)', 'oklch(0.98 0.012 85)'],
    vars: {
      '--bg':            'oklch(0.985 0.012 85)',
      '--bg-2':          'oklch(0.97 0.015 80)',
      '--bg-3':          'oklch(0.95 0.018 75)',
      '--border':        'oklch(0.90 0.020 75)',
      '--border-strong': 'oklch(0.82 0.025 70)',
      '--fg':            'oklch(0.22 0.015 60)',
      '--fg-2':          'oklch(0.38 0.018 60)',
      '--fg-3':          'oklch(0.55 0.016 60)',
      '--fg-4':          'oklch(0.70 0.014 65)',
      '--accent':        'oklch(0.55 0.15 60)',
      '--accent-2':      'oklch(0.46 0.16 55)',
      '--accent-soft':   'oklch(0.96 0.04 65)',
      '--accent-border': 'oklch(0.86 0.06 60)',
    },
  },
  warm: {
    name: 'Warm',
    blurb: 'Bone + coral — matches the Sidecar brand palette',
    swatches: ['oklch(0.65 0.17 35)', 'oklch(0.22 0.020 50)', 'oklch(0.97 0.012 80)'],
    vars: {
      '--bg':            'oklch(0.985 0.010 80)',
      '--bg-2':          'oklch(0.965 0.014 75)',
      '--bg-3':          'oklch(0.945 0.018 70)',
      '--border':        'oklch(0.90 0.020 70)',
      '--border-strong': 'oklch(0.82 0.025 65)',
      '--fg':            'oklch(0.22 0.020 50)',
      '--fg-2':          'oklch(0.38 0.022 55)',
      '--fg-3':          'oklch(0.55 0.020 55)',
      '--fg-4':          'oklch(0.70 0.018 60)',
      '--accent':        'oklch(0.62 0.17 35)',
      '--accent-2':      'oklch(0.52 0.18 30)',
      '--accent-soft':   'oklch(0.96 0.05 35)',
      '--accent-border': 'oklch(0.86 0.07 35)',
    },
  },
  sand: {
    name: 'Sand',
    blurb: 'Desert beige + terracotta',
    swatches: ['oklch(0.55 0.14 45)', 'oklch(0.24 0.022 60)', 'oklch(0.96 0.020 75)'],
    vars: {
      '--bg':            'oklch(0.97 0.020 75)',
      '--bg-2':          'oklch(0.94 0.025 70)',
      '--bg-3':          'oklch(0.91 0.030 65)',
      '--border':        'oklch(0.86 0.030 65)',
      '--border-strong': 'oklch(0.78 0.035 60)',
      '--fg':            'oklch(0.24 0.022 60)',
      '--fg-2':          'oklch(0.40 0.024 55)',
      '--fg-3':          'oklch(0.55 0.022 55)',
      '--fg-4':          'oklch(0.70 0.020 60)',
      '--accent':        'oklch(0.55 0.14 45)',
      '--accent-2':      'oklch(0.46 0.16 40)',
      '--accent-soft':   'oklch(0.93 0.06 50)',
      '--accent-border': 'oklch(0.82 0.08 45)',
    },
  },
  forest: {
    name: 'Forest',
    blurb: 'Linen + forest green — grounded, calm',
    swatches: ['oklch(0.50 0.13 155)', 'oklch(0.22 0.025 155)', 'oklch(0.97 0.010 120)'],
    vars: {
      '--bg':            'oklch(0.985 0.008 120)',
      '--bg-2':          'oklch(0.965 0.012 120)',
      '--bg-3':          'oklch(0.94 0.015 120)',
      '--border':        'oklch(0.90 0.014 120)',
      '--border-strong': 'oklch(0.82 0.018 120)',
      '--fg':            'oklch(0.22 0.025 155)',
      '--fg-2':          'oklch(0.38 0.022 155)',
      '--fg-3':          'oklch(0.55 0.018 155)',
      '--fg-4':          'oklch(0.70 0.014 145)',
      '--accent':        'oklch(0.48 0.14 155)',
      '--accent-2':      'oklch(0.40 0.15 155)',
      '--accent-soft':   'oklch(0.96 0.04 155)',
      '--accent-border': 'oklch(0.86 0.06 155)',
    },
  },
  mint: {
    name: 'Mint',
    blurb: 'Fresh, airy, slightly cyan',
    swatches: ['oklch(0.55 0.13 180)', 'oklch(0.22 0.025 195)', 'oklch(0.97 0.015 180)'],
    vars: {
      '--bg':            'oklch(0.985 0.012 175)',
      '--bg-2':          'oklch(0.965 0.016 175)',
      '--bg-3':          'oklch(0.94 0.018 175)',
      '--border':        'oklch(0.90 0.016 175)',
      '--border-strong': 'oklch(0.82 0.020 175)',
      '--fg':            'oklch(0.22 0.025 195)',
      '--fg-2':          'oklch(0.38 0.024 195)',
      '--fg-3':          'oklch(0.55 0.020 195)',
      '--fg-4':          'oklch(0.70 0.016 190)',
      '--accent':        'oklch(0.55 0.13 180)',
      '--accent-2':      'oklch(0.45 0.14 180)',
      '--accent-soft':   'oklch(0.96 0.04 180)',
      '--accent-border': 'oklch(0.86 0.06 180)',
    },
  },
  lavender: {
    name: 'Lavender',
    blurb: 'Soft purple-leaning neutral',
    swatches: ['oklch(0.55 0.15 290)', 'oklch(0.22 0.025 285)', 'oklch(0.97 0.012 295)'],
    vars: {
      '--bg':            'oklch(0.985 0.010 295)',
      '--bg-2':          'oklch(0.965 0.014 295)',
      '--bg-3':          'oklch(0.94 0.016 295)',
      '--border':        'oklch(0.90 0.014 295)',
      '--border-strong': 'oklch(0.82 0.018 295)',
      '--fg':            'oklch(0.22 0.025 285)',
      '--fg-2':          'oklch(0.38 0.022 285)',
      '--fg-3':          'oklch(0.55 0.018 285)',
      '--fg-4':          'oklch(0.70 0.014 290)',
      '--accent':        'oklch(0.55 0.15 290)',
      '--accent-2':      'oklch(0.46 0.17 290)',
      '--accent-soft':   'oklch(0.96 0.05 290)',
      '--accent-border': 'oklch(0.86 0.07 290)',
    },
  },
  plum: {
    name: 'Plum',
    blurb: 'Pearl + plum — premium, considered',
    swatches: ['oklch(0.52 0.17 340)', 'oklch(0.22 0.030 340)', 'oklch(0.97 0.008 330)'],
    vars: {
      '--bg':            'oklch(0.985 0.006 330)',
      '--bg-2':          'oklch(0.965 0.010 330)',
      '--bg-3':          'oklch(0.94 0.013 330)',
      '--border':        'oklch(0.90 0.012 330)',
      '--border-strong': 'oklch(0.82 0.016 330)',
      '--fg':            'oklch(0.22 0.030 340)',
      '--fg-2':          'oklch(0.38 0.025 340)',
      '--fg-3':          'oklch(0.55 0.020 340)',
      '--fg-4':          'oklch(0.70 0.015 335)',
      '--accent':        'oklch(0.50 0.17 340)',
      '--accent-2':      'oklch(0.42 0.18 340)',
      '--accent-soft':   'oklch(0.96 0.05 340)',
      '--accent-border': 'oklch(0.86 0.07 340)',
    },
  },
  cobalt: {
    name: 'Cobalt',
    blurb: 'Mist + electric cobalt — confident, energetic',
    swatches: ['oklch(0.55 0.20 250)', 'oklch(0.20 0.030 250)', 'oklch(0.98 0.005 240)'],
    vars: {
      '--bg':            'oklch(0.985 0.005 240)',
      '--bg-2':          'oklch(0.965 0.010 240)',
      '--bg-3':          'oklch(0.94 0.014 240)',
      '--border':        'oklch(0.90 0.014 240)',
      '--border-strong': 'oklch(0.82 0.018 240)',
      '--fg':            'oklch(0.20 0.030 250)',
      '--fg-2':          'oklch(0.36 0.025 250)',
      '--fg-3':          'oklch(0.55 0.020 250)',
      '--fg-4':          'oklch(0.70 0.014 245)',
      '--accent':        'oklch(0.52 0.20 250)',
      '--accent-2':      'oklch(0.42 0.22 250)',
      '--accent-soft':   'oklch(0.96 0.05 250)',
      '--accent-border': 'oklch(0.86 0.07 250)',
    },
  },
  slate: {
    name: 'Slate',
    blurb: 'Cool stone gray — neutral, professional',
    swatches: ['oklch(0.45 0.04 240)', 'oklch(0.20 0.015 240)', 'oklch(0.96 0.005 240)'],
    vars: {
      '--bg':            'oklch(0.975 0.005 240)',
      '--bg-2':          'oklch(0.94 0.008 240)',
      '--bg-3':          'oklch(0.91 0.010 240)',
      '--border':        'oklch(0.86 0.010 240)',
      '--border-strong': 'oklch(0.78 0.013 240)',
      '--fg':            'oklch(0.20 0.015 240)',
      '--fg-2':          'oklch(0.36 0.013 240)',
      '--fg-3':          'oklch(0.55 0.010 240)',
      '--fg-4':          'oklch(0.68 0.008 240)',
      '--accent':        'oklch(0.45 0.04 240)',
      '--accent-2':      'oklch(0.36 0.05 240)',
      '--accent-soft':   'oklch(0.92 0.014 240)',
      '--accent-border': 'oklch(0.82 0.020 240)',
    },
  },
  graphite: {
    name: 'Graphite',
    blurb: 'Dark mode — soft graphite + bright accent',
    swatches: ['oklch(0.68 0.18 252)', 'oklch(0.96 0.005 250)', 'oklch(0.20 0.012 250)'],
    vars: {
      '--bg':            'oklch(0.18 0.010 255)',
      '--bg-2':          'oklch(0.22 0.012 255)',
      '--bg-3':          'oklch(0.26 0.014 255)',
      '--border':        'oklch(0.30 0.012 255)',
      '--border-strong': 'oklch(0.38 0.014 255)',
      '--fg':            'oklch(0.96 0.005 250)',
      '--fg-2':          'oklch(0.82 0.010 250)',
      '--fg-3':          'oklch(0.65 0.010 250)',
      '--fg-4':          'oklch(0.50 0.010 250)',
      '--accent':        'oklch(0.68 0.18 252)',
      '--accent-2':      'oklch(0.78 0.15 252)',
      '--accent-soft':   'oklch(0.28 0.06 252)',
      '--accent-border': 'oklch(0.38 0.10 252)',
    },
  },
  ink: {
    name: 'Ink',
    blurb: 'Dark mode — warm near-black + coral accent',
    swatches: ['oklch(0.70 0.17 35)', 'oklch(0.96 0.008 80)', 'oklch(0.18 0.014 60)'],
    vars: {
      '--bg':            'oklch(0.16 0.012 60)',
      '--bg-2':          'oklch(0.20 0.014 60)',
      '--bg-3':          'oklch(0.24 0.016 60)',
      '--border':        'oklch(0.28 0.014 60)',
      '--border-strong': 'oklch(0.36 0.016 60)',
      '--fg':            'oklch(0.96 0.008 80)',
      '--fg-2':          'oklch(0.82 0.012 70)',
      '--fg-3':          'oklch(0.65 0.012 70)',
      '--fg-4':          'oklch(0.50 0.012 70)',
      '--accent':        'oklch(0.70 0.17 35)',
      '--accent-2':      'oklch(0.78 0.14 35)',
      '--accent-soft':   'oklch(0.30 0.08 35)',
      '--accent-border': 'oklch(0.40 0.12 35)',
    },
  },
};

const applyTheme = (themeId) => {
  const t = THEMES[themeId];
  if (!t) return;
  Object.entries(t.vars).forEach(([k, v]) => document.documentElement.style.setProperty(k, v));
  try { localStorage.setItem('sidecar.theme', themeId); } catch (e) {}
};

const useTheme = () => {
  const [theme, setTheme] = React.useState(() => {
    try { return localStorage.getItem('sidecar.theme') || 'warm'; } catch (e) { return 'warm'; }
  });
  React.useEffect(() => { applyTheme(theme); }, [theme]);
  return [theme, setTheme];
};

const ThemeSwitcher = () => {
  const [theme, setTheme] = useTheme();
  const [open, setOpen] = React.useState(false);
  const current = THEMES[theme];

  return (
    <div style={{
      position: 'fixed', bottom: 16, right: 16, zIndex: 50,
      display: 'flex', flexDirection: 'column-reverse', alignItems: 'flex-end', gap: 8,
    }}>
      {/* Trigger */}
      <button
        onClick={() => setOpen(o => !o)}
        style={{
          display: 'inline-flex', alignItems: 'center', gap: 8,
          padding: '7px 10px 7px 8px',
          background: 'var(--panel)',
          color: 'var(--fg-2)',
          border: '1px solid var(--border)',
          borderRadius: 999,
          fontSize: 12, fontWeight: 500,
          boxShadow: '0 8px 24px -8px rgba(15,22,36,0.12), 0 1px 0 rgba(15,22,36,0.04)',
          transition: 'border-color 120ms, box-shadow 120ms',
        }}
        onMouseEnter={e => { e.currentTarget.style.borderColor = 'var(--border-strong)'; }}
        onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--border)'; }}
      >
        <span style={{ display: 'inline-flex', gap: 2 }}>
          {current.swatches.map((s, i) => (
            <span key={i} style={{ width: 10, height: 10, borderRadius: '50%', background: s, border: '1px solid rgba(0,0,0,0.06)' }}/>
          ))}
        </span>
        <span>Theme · {current.name}</span>
        <Icon name="chevron-down" size={12} style={{ transform: open ? 'rotate(180deg)' : 'none', transition: 'transform 120ms' }}/>
      </button>

      {/* Panel */}
      {open ? (
        <div style={{
          width: 300, maxHeight: '70vh',
          background: 'var(--panel)',
          border: '1px solid var(--border)',
          borderRadius: 12,
          padding: 8,
          boxShadow: '0 24px 60px -16px rgba(15,22,36,0.18), 0 1px 0 rgba(15,22,36,0.04)',
          display: 'flex', flexDirection: 'column', gap: 2,
          overflowY: 'auto',
        }}>
          <div style={{
            padding: '6px 10px 4px',
            display: 'flex', alignItems: 'center', gap: 6,
          }}>
            <span style={{
              fontFamily: "'JetBrains Mono', monospace", fontSize: 9.5, fontWeight: 600,
              color: 'var(--fg-4)', letterSpacing: '0.10em', textTransform: 'uppercase',
            }}>Theme</span>
            <div style={{ flex: 1, height: 1, background: 'var(--border)', marginLeft: 4 }}/>
          </div>
          {Object.entries(THEMES).map(([id, t]) => {
            const active = theme === id;
            return (
              <button
                key={id}
                onClick={() => { setTheme(id); setOpen(false); }}
                style={{
                  display: 'flex', alignItems: 'center', gap: 10,
                  padding: '8px 10px', borderRadius: 7,
                  background: active ? 'var(--accent-soft)' : 'transparent',
                  border: active ? '1px solid var(--accent-border)' : '1px solid transparent',
                  color: active ? 'var(--accent-2)' : 'var(--fg-2)',
                  textAlign: 'left', cursor: 'pointer',
                  transition: 'background 120ms',
                }}
                onMouseEnter={e => { if (!active) e.currentTarget.style.background = 'var(--bg-3)'; }}
                onMouseLeave={e => { if (!active) e.currentTarget.style.background = 'transparent'; }}
              >
                <span style={{ display: 'inline-flex', gap: 2, flexShrink: 0 }}>
                  {t.swatches.map((s, i) => (
                    <span key={i} style={{ width: 12, height: 12, borderRadius: '50%', background: s, border: '1px solid rgba(0,0,0,0.06)' }}/>
                  ))}
                </span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--fg)', marginBottom: 1 }}>{t.name}</div>
                  <div style={{ fontSize: 11, color: 'var(--fg-3)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.blurb}</div>
                </div>
                {active ? <Icon name="check" size={14} style={{ color: 'var(--accent-2)', flexShrink: 0 }}/> : null}
              </button>
            );
          })}
        </div>
      ) : null}
    </div>
  );
};

Object.assign(window, { ThemeSwitcher });
