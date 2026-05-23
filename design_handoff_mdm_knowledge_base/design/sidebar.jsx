// Left sidebar — conversations list

// Per-nav-item color tokens — one hue each so the nav reads at a glance.
const NAV_TONES = {
  accent:  { fg: 'oklch(0.50 0.14 252)', bg: 'oklch(0.95 0.04 252)', bd: 'oklch(0.86 0.06 252)' },
  violet:  { fg: 'oklch(0.50 0.16 295)', bg: 'oklch(0.95 0.04 295)', bd: 'oklch(0.86 0.07 295)' },
  teal:    { fg: 'oklch(0.50 0.12 195)', bg: 'oklch(0.95 0.04 195)', bd: 'oklch(0.86 0.06 195)' },
  amber:   { fg: 'oklch(0.50 0.13 70)',  bg: 'oklch(0.96 0.05 80)',  bd: 'oklch(0.87 0.07 75)' },
  rose:    { fg: 'oklch(0.55 0.16 15)',  bg: 'oklch(0.96 0.04 15)',  bd: 'oklch(0.87 0.06 15)' },
  green:   { fg: 'oklch(0.50 0.13 155)', bg: 'oklch(0.96 0.04 155)', bd: 'oklch(0.86 0.06 155)' },
};

// Color tags for conversations — domain markers users can scan by hue.
const CONV_TAGS = {
  c1:  { tone: 'accent', label: 'Customer' },
  c2:  { tone: 'accent', label: 'Customer' },
  c3:  { tone: 'violet', label: 'Product' },
  c4:  { tone: 'amber',  label: 'Stewardship' },
  c5:  { tone: 'teal',   label: 'Vendor' },
  c6:  { tone: 'rose',   label: 'Privacy' },
  c7:  { tone: 'green',  label: 'Platform' },
  c8:  { tone: 'violet', label: 'Finance' },
  c9:  { tone: 'teal',   label: 'Vendor' },
  c10: { tone: 'accent', label: 'Customer' },
};

const Sidebar = ({ activeId, onSelect, collapsed }) => {
  const { conversations } = window.MDM_DATA;
  const [query, setQuery] = React.useState('');
  const pinned = conversations.filter(c => c.pinned);
  const grouped = conversations.filter(c => !c.pinned).reduce((acc, c) => {
    (acc[c.date] = acc[c.date] || []).push(c);
    return acc;
  }, {});

  if (collapsed) return null;

  return (
    <aside style={{
      width: 280, flexShrink: 0,
      borderRight: '1px solid var(--border)',
      background: 'var(--bg-2)',
      display: 'flex', flexDirection: 'column',
      height: '100%',
      position: 'relative',
    }}>
      {/* Soft color wash behind the header */}
      <div aria-hidden style={{
        position: 'absolute', top: 0, left: 0, right: 0, height: 220,
        background:
          'radial-gradient(120% 80% at 0% 0%, oklch(0.93 0.07 252 / 0.55), transparent 60%),' +
          'radial-gradient(80% 70% at 100% 0%, oklch(0.93 0.07 295 / 0.40), transparent 60%)',
        pointerEvents: 'none',
        zIndex: 0,
      }}/>

      <div style={{ position: 'relative', zIndex: 1, display: 'flex', flexDirection: 'column', flex: 1, minHeight: 0 }}>
        {/* Brand */}
        <div style={{ padding: '14px 14px 10px', display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{
            width: 30, height: 30, borderRadius: 8,
            background: 'conic-gradient(from 210deg at 50% 50%, oklch(0.55 0.18 252), oklch(0.58 0.18 295), oklch(0.62 0.16 195), oklch(0.55 0.18 252))',
            color: 'white',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            fontWeight: 700, fontSize: 13.5, letterSpacing: '-0.03em',
            boxShadow: '0 1px 0 rgba(15,22,36,0.06), 0 4px 14px -4px oklch(0.55 0.18 270 / 0.45)',
          }}>M</div>
          <div style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.15 }}>
            <span style={{ fontWeight: 600, fontSize: 13.5, letterSpacing: '-0.01em' }}>Master Data Hub</span>
            <span style={{
              fontSize: 11, color: 'var(--fg-3)',
              display: 'inline-flex', alignItems: 'center', gap: 5,
            }}>
              <span style={{
                width: 6, height: 6, borderRadius: '50%',
                background: 'oklch(0.62 0.13 155)',
                boxShadow: '0 0 0 3px oklch(0.62 0.13 155 / 0.18)',
              }}/>
              Knowledge Base · GA
            </span>
          </div>
          <div style={{ marginLeft: 'auto' }}>
            <IconButton icon="more" label="Workspace menu" />
          </div>
        </div>

        {/* New chat */}
        <div style={{ padding: '4px 12px 10px' }}>
          <button
            style={{
              width: '100%', display: 'flex', alignItems: 'center', gap: 8,
              padding: '9px 11px',
              background: 'linear-gradient(180deg, oklch(0.55 0.16 252), oklch(0.46 0.16 258))',
              color: 'white',
              border: '1px solid oklch(0.40 0.16 258)',
              borderRadius: 9,
              fontSize: 13, fontWeight: 500,
              boxShadow: '0 1px 0 oklch(0.99 0 0 / 0.30) inset, 0 6px 16px -6px oklch(0.40 0.16 258 / 0.55)',
              transition: 'transform 120ms, box-shadow 120ms',
            }}
            onMouseEnter={e => { e.currentTarget.style.boxShadow = '0 1px 0 oklch(0.99 0 0 / 0.30) inset, 0 10px 22px -6px oklch(0.40 0.16 258 / 0.60)'; }}
            onMouseLeave={e => { e.currentTarget.style.boxShadow = '0 1px 0 oklch(0.99 0 0 / 0.30) inset, 0 6px 16px -6px oklch(0.40 0.16 258 / 0.55)'; }}
          >
            <Icon name="plus" size={15} stroke={2}/>
            <span>New conversation</span>
            <span className="mono" style={{
              marginLeft: 'auto', fontSize: 10.5,
              color: 'oklch(0.99 0 0 / 0.75)',
              background: 'oklch(0.99 0 0 / 0.15)',
              padding: '1px 5px', borderRadius: 4,
            }}>⌘N</span>
          </button>
        </div>

        {/* Search */}
        <div style={{ padding: '0 12px 12px' }}>
          <div style={{
            display: 'flex', alignItems: 'center', gap: 8,
            padding: '7px 10px',
            background: 'var(--panel)',
            border: '1px solid var(--border)',
            borderRadius: 8,
            boxShadow: 'var(--shadow-sm)',
          }}>
            <Icon name="search" size={14} style={{ color: 'var(--fg-3)' }}/>
            <input
              value={query}
              onChange={e => setQuery(e.target.value)}
              placeholder="Search conversations"
              style={{
                flex: 1, background: 'transparent', border: 0, outline: 0,
                fontSize: 13, color: 'var(--fg)'
              }}
            />
            <span className="mono" style={{
              fontSize: 10, color: 'var(--fg-4)',
              border: '1px solid var(--border)',
              padding: '0 4px', borderRadius: 3,
            }}>⌘K</span>
          </div>
        </div>

        {/* Nav */}
        <div style={{ padding: '0 8px 6px', display: 'flex', flexDirection: 'column', gap: 1 }}>
          {[
            { icon: 'sparkle', label: 'Ask the hub',          tone: 'accent', badge: null,    active: true },
            { icon: 'book',    label: 'Knowledge sources',    tone: 'violet', badge: '1,247' },
            { icon: 'graph',   label: 'Data model explorer',  tone: 'teal' },
            { icon: 'flow',    label: 'Stewardship queue',    tone: 'amber',  badge: '38' },
            { icon: 'history', label: 'Audit log',            tone: 'green' },
          ].map(item => {
            const t = NAV_TONES[item.tone];
            return (
              <button key={item.label} style={{
                display: 'flex', alignItems: 'center', gap: 10,
                padding: '6px 8px', borderRadius: 7,
                background: item.active ? 'var(--panel)' : 'transparent',
                border: item.active ? '1px solid var(--border)' : '1px solid transparent',
                boxShadow: item.active ? 'var(--shadow-sm)' : 'none',
                color: item.active ? 'var(--fg)' : 'var(--fg-2)',
                fontSize: 13, fontWeight: item.active ? 500 : 400,
                textAlign: 'left',
                transition: 'background 120ms',
              }}
              onMouseEnter={e => { if (!item.active) e.currentTarget.style.background = 'oklch(0.99 0 0 / 0.6)'; }}
              onMouseLeave={e => { if (!item.active) e.currentTarget.style.background = 'transparent'; }}
              >
                <span style={{
                  width: 24, height: 24, borderRadius: 6,
                  background: t.bg, color: t.fg,
                  border: `1px solid ${t.bd}`,
                  display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                  flexShrink: 0,
                }}>
                  <Icon name={item.icon} size={13} stroke={1.9}/>
                </span>
                <span>{item.label}</span>
                {item.badge ? (
                  <span style={{
                    marginLeft: 'auto',
                    fontSize: 10.5,
                    color: t.fg,
                    background: t.bg,
                    border: `1px solid ${t.bd}`,
                    padding: '1px 6px',
                    borderRadius: 999,
                    fontWeight: 600,
                    fontVariantNumeric: 'tabular-nums',
                  }}>{item.badge}</span>
                ) : null}
              </button>
            );
          })}
        </div>

        {/* Conversations */}
        <div style={{ flex: 1, overflowY: 'auto', padding: '4px 8px 12px', minHeight: 0 }}>
          {/* Pinned */}
          <div style={{ padding: '10px 10px 4px', display: 'flex', alignItems: 'center', gap: 6 }}>
            <Icon name="pin" size={11} stroke={2} style={{ color: 'oklch(0.50 0.13 70)' }}/>
            <span style={{ fontSize: 10.5, fontWeight: 600, color: 'var(--fg-4)', textTransform: 'uppercase', letterSpacing: '0.08em' }}>
              Pinned
            </span>
            <div style={{ flex: 1, height: 1, background: 'var(--border)', marginLeft: 6 }}/>
          </div>
          {pinned.map(c => (
            <ConversationRow key={c.id} c={c} active={c.id === activeId} onClick={() => onSelect(c.id)} />
          ))}

          {Object.entries(grouped).map(([date, items]) => (
            <React.Fragment key={date}>
              <div style={{ padding: '14px 10px 4px', display: 'flex', alignItems: 'center', gap: 6 }}>
                <span style={{ fontSize: 10.5, fontWeight: 600, color: 'var(--fg-4)', textTransform: 'uppercase', letterSpacing: '0.08em' }}>{date}</span>
                <div style={{ flex: 1, height: 1, background: 'var(--border)', marginLeft: 6 }}/>
              </div>
              {items.map(c => (
                <ConversationRow key={c.id} c={c} active={c.id === activeId} onClick={() => onSelect(c.id)} />
              ))}
            </React.Fragment>
          ))}
        </div>

        {/* User */}
        <div style={{
          borderTop: '1px solid var(--border)',
          padding: '10px 12px',
          display: 'flex', alignItems: 'center', gap: 10,
          background: 'linear-gradient(180deg, var(--bg-2), oklch(0.96 0.015 252))',
        }}>
          <div style={{ position: 'relative' }}>
            <div style={{
              width: 30, height: 30, borderRadius: '50%',
              background: 'linear-gradient(135deg, oklch(0.55 0.16 295), oklch(0.50 0.15 252))',
              color: 'white',
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 11.5, fontWeight: 600, letterSpacing: '0.02em',
            }}>AV</div>
            <span style={{
              position: 'absolute', right: -1, bottom: -1,
              width: 10, height: 10, borderRadius: '50%',
              background: 'oklch(0.62 0.13 155)',
              border: '2px solid var(--bg-2)',
            }}/>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.2, minWidth: 0, flex: 1 }}>
            <span style={{ fontSize: 12.5, fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>Amelia Voss</span>
            <span style={{ fontSize: 11, color: 'var(--fg-3)' }}>Data Steward · EMEA</span>
          </div>
          <IconButton icon="more" label="Account"/>
        </div>
      </div>
    </aside>
  );
};

const ConversationRow = ({ c, active, onClick }) => {
  const tag = CONV_TAGS[c.id] || { tone: 'accent', label: 'General' };
  const t = NAV_TONES[tag.tone];
  return (
    <button onClick={onClick} style={{
      position: 'relative',
      width: '100%', display: 'block', textAlign: 'left',
      padding: '8px 10px 8px 14px', borderRadius: 7,
      background: active ? 'var(--panel)' : 'transparent',
      border: active ? '1px solid var(--border)' : '1px solid transparent',
      boxShadow: active ? 'var(--shadow-sm)' : 'none',
      marginBottom: 1,
      transition: 'background 120ms',
    }}
    onMouseEnter={e => { if (!active) e.currentTarget.style.background = 'oklch(0.99 0 0 / 0.6)'; }}
    onMouseLeave={e => { if (!active) e.currentTarget.style.background = 'transparent'; }}
    >
      {/* color rail */}
      <span style={{
        position: 'absolute', left: 5, top: 10, bottom: 10,
        width: 3, borderRadius: 2,
        background: active ? t.fg : t.bd,
        opacity: active ? 1 : 0.85,
      }}/>
      <div style={{
        fontSize: 13, fontWeight: active ? 500 : 400,
        color: 'var(--fg)',
        overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
        marginBottom: 2,
      }}>{c.title}</div>
      <div style={{
        display: 'flex', alignItems: 'center', gap: 6,
        fontSize: 11.5, color: 'var(--fg-3)',
      }}>
        <span style={{
          flexShrink: 0,
          fontSize: 10, fontWeight: 600,
          color: t.fg, background: t.bg,
          border: `1px solid ${t.bd}`,
          padding: '0 5px', borderRadius: 3,
          letterSpacing: '0.02em',
        }}>{tag.label}</span>
        <span style={{
          overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
        }}>{c.preview}</span>
      </div>
    </button>
  );
};

Object.assign(window, { Sidebar });
