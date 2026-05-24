// Left sidebar — conversations list

const Sidebar = ({ activeId, onSelect, collapsed }) => {
  const { conversations } = window.MDM_DATA;
  const [query, setQuery] = React.useState('');
  const [hoveredNav, setHoveredNav] = React.useState(null);

  const pinned = conversations.filter(c => c.pinned);
  const grouped = conversations.filter(c => !c.pinned).reduce((acc, c) => {
    (acc[c.date] = acc[c.date] || []).push(c);
    return acc;
  }, {});

  if (collapsed) return null;

  return (
    <aside style={{
      width: 296, flexShrink: 0,
      borderRight: '1px solid var(--border)',
      background: 'var(--bg-2)',
      display: 'flex', flexDirection: 'column',
      height: '100%',
      position: 'relative',
    }}>
      {/* Soft color wash behind the header */}
      <div aria-hidden style={{
        position: 'absolute', top: 0, left: 0, right: 0, height: 200,
        background:
          'radial-gradient(110% 80% at 0% 0%, oklch(0.95 0.05 35 / 0.45), transparent 60%),' +
          'radial-gradient(90% 70% at 100% 0%, oklch(0.94 0.05 60 / 0.30), transparent 60%)',
        pointerEvents: 'none', zIndex: 0,
      }}/>

      <div style={{ position: 'relative', zIndex: 1, display: 'flex', flexDirection: 'column', flex: 1, minHeight: 0 }}>
        {/* Brand — Concept 1 (Buddy duo) + slogan */}
        <div style={{ padding: '16px 14px 12px', display: 'flex', alignItems: 'center', gap: 12 }}>
          <SidecarMarkBuddy size={38} big="oklch(0.65 0.17 35)" small="oklch(0.22 0.020 50)"/>
          <div style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.15, gap: 2, minWidth: 0 }}>
            <SidecarWordmark size={22} color="var(--fg)" accent="oklch(0.65 0.17 35)"/>
            <span style={{
              fontSize: 11, color: 'var(--fg-3)',
              fontStyle: 'italic',
              letterSpacing: '0.005em',
              whiteSpace: 'nowrap',
            }}>fetches what you need.</span>
          </div>
          <div style={{ marginLeft: 'auto' }}>
            <IconButton icon="more" label="Workspace menu"/>
          </div>
        </div>

        {/* New chat */}
        <div style={{ padding: '4px 12px 10px' }}>
          <button
            style={{
              width: '100%', display: 'flex', alignItems: 'center', gap: 8,
              padding: '9px 11px',
              background: 'linear-gradient(180deg, oklch(0.70 0.17 35), oklch(0.60 0.18 32))',
              color: 'white',
              border: '1px solid oklch(0.52 0.18 30)',
              borderRadius: 9,
              fontSize: 13, fontWeight: 500,
              boxShadow: '0 1px 0 oklch(0.99 0 0 / 0.30) inset, 0 6px 16px -6px oklch(0.55 0.18 30 / 0.55)',
              transition: 'box-shadow 160ms, transform 160ms',
            }}
            onMouseEnter={e => {
              e.currentTarget.style.boxShadow = '0 1px 0 oklch(0.99 0 0 / 0.30) inset, 0 10px 22px -6px oklch(0.55 0.18 30 / 0.60)';
              e.currentTarget.style.transform = 'translateY(-1px)';
            }}
            onMouseLeave={e => {
              e.currentTarget.style.boxShadow = '0 1px 0 oklch(0.99 0 0 / 0.30) inset, 0 6px 16px -6px oklch(0.55 0.18 30 / 0.55)';
              e.currentTarget.style.transform = 'translateY(0)';
            }}
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
            background: 'var(--panel)', border: '1px solid var(--border)',
            borderRadius: 8, boxShadow: 'var(--shadow-sm)',
            transition: 'border-color 120ms',
          }}
          onMouseEnter={e => e.currentTarget.style.borderColor = 'var(--border-strong)'}
          onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--border)'}
          >
            <Icon name="search" size={14} style={{ color: 'var(--fg-3)' }}/>
            <input
              value={query}
              onChange={e => setQuery(e.target.value)}
              placeholder="Search conversations"
              style={{ flex: 1, background: 'transparent', border: 0, outline: 0, fontSize: 13, color: 'var(--fg)' }}
            />
            <span className="mono" style={{
              fontSize: 10, color: 'var(--fg-4)',
              border: '1px solid var(--border)',
              padding: '0 4px', borderRadius: 3,
            }}>⌘K</span>
          </div>
        </div>

        {/* Nav */}
        <NavList hoveredNav={hoveredNav} setHoveredNav={setHoveredNav}/>

        {/* Conversations */}
        <div style={{ flex: 1, overflowY: 'auto', padding: '4px 8px 12px', minHeight: 0 }}>
          <SectionHeader icon="pin" label="Pinned" tone="oklch(0.55 0.16 35)"/>
          {pinned.map(c => (
            <ConversationRow key={c.id} c={c} active={c.id === activeId} onClick={() => onSelect(c.id)}/>
          ))}

          {Object.entries(grouped).map(([date, items]) => (
            <React.Fragment key={date}>
              <SectionHeader label={date}/>
              {items.map(c => (
                <ConversationRow key={c.id} c={c} active={c.id === activeId} onClick={() => onSelect(c.id)}/>
              ))}
            </React.Fragment>
          ))}
        </div>

        {/* User */}
        <UserFooter/>
      </div>
    </aside>
  );
};

// -- Nav --
const NAV_ITEMS = [
  { id: 'ask',        icon: 'sparkle', label: 'Ask Sidecar',       tone: 'oklch(0.55 0.17 35)',  bg: 'oklch(0.96 0.05 35)',  bd: 'oklch(0.87 0.07 35)',  active: true },
  { id: 'sources',    icon: 'book',    label: 'Knowledge sources', tone: 'oklch(0.50 0.16 295)', bg: 'oklch(0.95 0.04 295)', bd: 'oklch(0.86 0.07 295)', badge: '1,247' },
  { id: 'model',      icon: 'graph',   label: 'Data model',        tone: 'oklch(0.50 0.12 195)', bg: 'oklch(0.95 0.04 195)', bd: 'oklch(0.86 0.06 195)' },
  { id: 'queue',      icon: 'flow',    label: 'Stewardship queue', tone: 'oklch(0.50 0.13 70)',  bg: 'oklch(0.96 0.05 80)',  bd: 'oklch(0.87 0.07 75)',  badge: '38' },
  { id: 'audit',      icon: 'history', label: 'Audit log',         tone: 'oklch(0.50 0.13 155)', bg: 'oklch(0.96 0.04 155)', bd: 'oklch(0.86 0.06 155)' },
];

const NavList = ({ hoveredNav, setHoveredNav }) => (
  <div style={{ padding: '0 8px 6px', display: 'flex', flexDirection: 'column', gap: 2 }}>
    {NAV_ITEMS.map(item => {
      const isHover = hoveredNav === item.id;
      const isActive = item.active;
      return (
        <button
          key={item.id}
          onMouseEnter={() => setHoveredNav(item.id)}
          onMouseLeave={() => setHoveredNav(null)}
          style={{
            position: 'relative',
            display: 'flex', alignItems: 'center', gap: 10,
            padding: '7px 10px', borderRadius: 8,
            background: isActive
              ? 'var(--panel)'
              : isHover
                ? item.bg
                : 'transparent',
            border: isActive
              ? '1px solid var(--border)'
              : isHover
                ? `1px solid ${item.bd}`
                : '1px solid transparent',
            boxShadow: isActive ? 'var(--shadow-sm)' : 'none',
            color: isActive ? 'var(--fg)' : isHover ? item.tone : 'var(--fg-2)',
            fontSize: 13,
            fontWeight: isActive || isHover ? 500 : 400,
            textAlign: 'left',
            transition: 'background 140ms, color 140ms, border-color 140ms, box-shadow 140ms',
            overflow: 'hidden',
          }}
        >
          {/* slide-in indicator */}
          <span style={{
            position: 'absolute', left: 0, top: 8, bottom: 8,
            width: 3, borderRadius: 2,
            background: item.tone,
            opacity: isActive ? 1 : isHover ? 0.6 : 0,
            transform: isActive ? 'translateX(-1px)' : isHover ? 'translateX(0)' : 'translateX(-4px)',
            transition: 'opacity 160ms, transform 160ms',
          }}/>
          <span style={{
            width: 26, height: 26, borderRadius: 7,
            background: isActive || isHover ? item.bg : 'transparent',
            color: isActive || isHover ? item.tone : 'var(--fg-3)',
            border: `1px solid ${isActive || isHover ? item.bd : 'transparent'}`,
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            flexShrink: 0,
            transition: 'background 140ms, color 140ms, border-color 140ms',
          }}>
            <Icon name={item.icon} size={14} stroke={1.9}/>
          </span>
          <span style={{
            flex: 1,
            transform: isHover && !isActive ? 'translateX(2px)' : 'translateX(0)',
            transition: 'transform 160ms',
          }}>{item.label}</span>
          {item.badge ? (
            <span style={{
              fontSize: 10.5, fontWeight: 600,
              color: item.tone,
              background: item.bg,
              border: `1px solid ${item.bd}`,
              padding: '1px 6px',
              borderRadius: 999,
              fontVariantNumeric: 'tabular-nums',
            }}>{item.badge}</span>
          ) : null}
        </button>
      );
    })}
  </div>
);

// -- Section header --
const SectionHeader = ({ icon, label, tone }) => (
  <div style={{ padding: '12px 10px 4px', display: 'flex', alignItems: 'center', gap: 6 }}>
    {icon ? <Icon name={icon} size={11} stroke={2} style={{ color: tone || 'var(--fg-4)' }}/> : null}
    <span style={{
      fontSize: 10.5, fontWeight: 600,
      color: 'var(--fg-4)',
      textTransform: 'uppercase', letterSpacing: '0.08em',
    }}>{label}</span>
    <div style={{ flex: 1, height: 1, background: 'var(--border)', marginLeft: 6 }}/>
  </div>
);

// -- Conversation row --
const ConversationRow = ({ c, active, onClick }) => {
  const [hover, setHover] = React.useState(false);
  const v = VENDORS[c.vendor];
  if (!v) return null;
  const t1 = vendorTone(v.hue, v.c, 1);

  return (
    <button
      onClick={onClick}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      style={{
        position: 'relative',
        width: '100%', display: 'block', textAlign: 'left',
        padding: '9px 10px 9px 16px', borderRadius: 8,
        background: active
          ? 'var(--panel)'
          : hover
            ? `oklch(0.99 0.005 80)`
            : 'transparent',
        border: active ? '1px solid var(--border)' : `1px solid ${hover ? t1.bd : 'transparent'}`,
        boxShadow: active ? 'var(--shadow-sm)' : 'none',
        marginBottom: 2,
        transition: 'background 140ms, border-color 140ms, transform 140ms',
        transform: hover && !active ? 'translateX(1px)' : 'translateX(0)',
      }}
    >
      {/* vendor rail */}
      <span style={{
        position: 'absolute', left: 6, top: 10, bottom: 10,
        width: 3, borderRadius: 2,
        background: t1.dot,
        opacity: active ? 1 : hover ? 0.95 : 0.55,
        transition: 'opacity 140ms',
      }}/>

      {/* row 1: vendor pill + title */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4 }}>
        <VendorPill vendor={c.vendor} level={1} mono>{v.name}</VendorPill>
        {v.kind !== 'vendor' ? <KindBadge kind={v.kind} style={{ fontSize: 8.5 }}/> : null}
      </div>

      <div style={{
        fontSize: 13, fontWeight: active ? 500 : 400,
        color: 'var(--fg)',
        overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
        marginBottom: 4,
      }}>{c.title}</div>

      {/* row 3: product › domain › ext */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 3, flexWrap: 'nowrap', overflow: 'hidden' }}>
        {c.product ? <VendorPill vendor={c.vendor} level={2}>{c.product}</VendorPill> : null}
        {c.domain  ? <><Caret/><VendorPill vendor={c.vendor} level={3}>{c.domain}</VendorPill></> : null}
        {c.extension ? <><Caret/><VendorPill vendor={c.vendor} level={4}>{c.extension}</VendorPill></> : null}
      </div>
    </button>
  );
};

// -- User footer --
const UserFooter = () => (
  <div style={{
    borderTop: '1px solid var(--border)',
    padding: '10px 12px',
    display: 'flex', alignItems: 'center', gap: 10,
    background: 'linear-gradient(180deg, var(--bg-2), oklch(0.96 0.020 50))',
  }}>
    <div style={{ position: 'relative' }}>
      <div style={{
        width: 30, height: 30, borderRadius: '50%',
        background: 'linear-gradient(135deg, oklch(0.55 0.16 35), oklch(0.45 0.18 30))',
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
);

Object.assign(window, { Sidebar });
