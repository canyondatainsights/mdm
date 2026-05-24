// Chat area — header, messages, composer

const ChatHeader = ({ onToggleInspector, inspectorOpen, onToggleSidebar, sidebarCollapsed }) => (
  <div style={{
    height: 52, flexShrink: 0,
    borderBottom: '1px solid var(--border)',
    display: 'flex', alignItems: 'center',
    padding: '0 16px', gap: 10,
    background: 'var(--bg)',
  }}>
    <IconButton icon="sidebar" label="Toggle sidebar" onClick={onToggleSidebar} active={!sidebarCollapsed}/>
    <div style={{ width: 1, height: 18, background: 'var(--border)' }}/>

    <div style={{ display: 'flex', alignItems: 'center', gap: 8, minWidth: 0 }}>
      <span style={{ fontSize: 13.5, fontWeight: 500, letterSpacing: '-0.01em', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
        Customer golden record reconciliation
      </span>
      <Pill tone="accent" icon="sparkle" size="xs">Reasoning · v2</Pill>
    </div>

    <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 6 }}>
      <Pill icon="shield" tone="ok" size="xs">PII redacted</Pill>
      <Pill icon="database" size="xs">Domain · Customer</Pill>
      <div style={{ width: 1, height: 18, background: 'var(--border)', margin: '0 4px' }}/>
      <IconButton icon="link" label="Share"/>
      <IconButton icon="more" label="More"/>
      <IconButton icon="panel" label="Toggle sources panel" onClick={onToggleInspector} active={inspectorOpen}/>
    </div>
  </div>
);

const renderInline = (text) => {
  // very small bold parser for **word**
  const parts = text.split(/(\*\*[^*]+\*\*)/g);
  return parts.map((p, i) => {
    if (p.startsWith('**') && p.endsWith('**')) {
      return <strong key={i} style={{ fontWeight: 600, color: 'var(--fg)' }}>{p.slice(2, -2)}</strong>;
    }
    return <React.Fragment key={i}>{p}</React.Fragment>;
  });
};

const UserMessage = ({ m }) => (
  <div style={{ display: 'flex', justifyContent: 'flex-end', padding: '8px 0' }}>
    <div style={{ maxWidth: '78%', display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 4 }}>
      <div style={{
        background: 'var(--accent)',
        color: 'white',
        padding: '11px 14px',
        borderRadius: '14px 14px 4px 14px',
        fontSize: 14, lineHeight: 1.55,
        boxShadow: '0 1px 0 rgba(15,22,36,0.05)',
      }}>
        {m.content}
      </div>
      <span style={{ fontSize: 11, color: 'var(--fg-4)' }}>You · {m.time}</span>
    </div>
  </div>
);

const CitationChip = ({ index, source, onOpen }) => (
  <button
    onClick={() => onOpen(source.id)}
    style={{
      display: 'inline-flex', alignItems: 'center', gap: 4,
      verticalAlign: 'baseline',
      padding: '0 5px',
      height: 16,
      background: 'var(--accent-soft)', color: 'var(--accent-2)',
      border: '1px solid var(--accent-border)',
      borderRadius: 4,
      fontSize: 10, fontWeight: 600,
      cursor: 'pointer',
      marginLeft: 2,
      lineHeight: 1,
    }}
    title={source.title}
  >
    {index + 1}
  </button>
);

const AssistantMessage = ({ m, onOpenSource }) => {
  const { sources } = window.MDM_DATA;
  const citedSources = m.citations.map(c => ({ ...c, doc: sources.find(s => s.id === c.id) }));

  return (
    <div style={{ display: 'flex', padding: '12px 0', gap: 12 }}>
      {/* Bot avatar */}
      <div style={{
        width: 30, height: 30, flexShrink: 0,
        borderRadius: 7,
        background: 'linear-gradient(135deg, var(--accent), var(--accent-2))',
        color: 'white',
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        boxShadow: 'var(--shadow-sm)',
      }}>
        <SidecarMark size={18} color="white" accent="oklch(0.85 0.04 252)"/>
      </div>

      <div style={{ flex: 1, minWidth: 0 }}>
        {/* Header line */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
          <SidecarWordmark size={14} color="var(--fg)" accent="var(--accent)"/>
          <Pill tone="ok" size="xs" icon="check">High confidence</Pill>
          <span style={{ fontSize: 11.5, color: 'var(--fg-4)' }}>· {m.sources_used} sources · {m.time}</span>
        </div>

        {/* Body blocks */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12, fontSize: 14, lineHeight: 1.6, color: 'var(--fg-2)' }}>
          {m.content.map((b, i) => {
            if (b.type === 'p') {
              const inject = i === 0 ? <CitationChip index={0} source={citedSources[0].doc} onOpen={onOpenSource}/> : null;
              return <p key={i} style={{ margin: 0 }}>{renderInline(b.text)}{inject}</p>;
            }
            if (b.type === 'ol') {
              return (
                <ol key={i} style={{ margin: 0, paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 6 }}>
                  {b.items.map((item, j) => (
                    <li key={j} style={{ paddingLeft: 4 }}>
                      {renderInline(item)}
                      {j < citedSources.length ? <CitationChip index={j} source={citedSources[j].doc} onOpen={onOpenSource}/> : null}
                    </li>
                  ))}
                </ol>
              );
            }
            if (b.type === 'callout') {
              return (
                <div key={i} style={{
                  display: 'flex', gap: 10,
                  padding: '10px 12px',
                  background: 'oklch(0.97 0.03 80)',
                  border: '1px solid oklch(0.88 0.05 80)',
                  borderRadius: 8,
                  fontSize: 13.5,
                  color: 'oklch(0.34 0.08 70)',
                }}>
                  <Icon name="sparkle" size={14} stroke={2} style={{ marginTop: 3, flexShrink: 0, color: 'oklch(0.48 0.12 70)' }}/>
                  <div>{renderInline(b.text)}</div>
                </div>
              );
            }
            if (b.type === 'options') {
              return (
                <div key={i} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                  {b.items.map((opt, j) => (
                    <div key={j} style={{
                      padding: '12px 13px',
                      background: 'var(--panel)',
                      border: '1px solid var(--border)',
                      borderRadius: 10,
                      display: 'flex', flexDirection: 'column', gap: 6,
                    }}>
                      <div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--fg)' }}>{opt.label}</div>
                      <div style={{ fontSize: 12.5, color: 'var(--fg-2)', lineHeight: 1.5 }}>{opt.body}</div>
                    </div>
                  ))}
                </div>
              );
            }
            return null;
          })}
        </div>

        {/* Citations block */}
        <CitationsList citations={citedSources} onOpen={onOpenSource}/>

        {/* Toolbar */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 2, marginTop: 12 }}>
          {[
            { icon: 'copy', label: 'Copy' },
            { icon: 'refresh', label: 'Regenerate' },
            { icon: 'thumbsup', label: 'Helpful' },
            { icon: 'thumbsdown', label: 'Not helpful' },
          ].map(b => <IconButton key={b.label} icon={b.icon} label={b.label}/>)}
          <div style={{ width: 1, height: 16, background: 'var(--border)', margin: '0 4px' }}/>
          <button style={{
            display: 'inline-flex', alignItems: 'center', gap: 5,
            padding: '4px 8px',
            background: 'transparent', color: 'var(--fg-2)',
            border: '1px solid var(--border)', borderRadius: 6,
            fontSize: 12, fontWeight: 500,
          }}>
            <Icon name="flow" size={13}/>
            <span>Create Jira ticket</span>
          </button>
          <button style={{
            display: 'inline-flex', alignItems: 'center', gap: 5,
            padding: '4px 8px',
            background: 'transparent', color: 'var(--fg-2)',
            border: '1px solid var(--border)', borderRadius: 6,
            fontSize: 12, fontWeight: 500,
          }}>
            <Icon name="external" size={13}/>
            <span>Open in stewardship</span>
          </button>
        </div>
      </div>
    </div>
  );
};

const CitationsList = ({ citations, onOpen }) => (
  <div style={{
    marginTop: 14,
    padding: '10px 12px',
    background: 'var(--bg-2)',
    border: '1px solid var(--border)',
    borderRadius: 10,
  }}>
    <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
      <Icon name="book" size={13} style={{ color: 'var(--fg-3)' }}/>
      <span style={{ fontSize: 11, fontWeight: 600, color: 'var(--fg-3)', textTransform: 'uppercase', letterSpacing: '0.07em' }}>
        Sources
      </span>
    </div>
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      {citations.map((c, i) => (
        <button key={c.id} onClick={() => onOpen(c.id)} style={{
          display: 'flex', alignItems: 'flex-start', gap: 10,
          padding: '8px 9px',
          background: 'var(--panel)',
          border: '1px solid var(--border)',
          borderRadius: 7,
          textAlign: 'left', width: '100%',
          transition: 'border-color 120ms, background 120ms',
        }}
        onMouseEnter={e => { e.currentTarget.style.borderColor = 'var(--accent-border)'; e.currentTarget.style.background = 'var(--accent-soft)'; }}
        onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--border)'; e.currentTarget.style.background = 'var(--panel)'; }}
        >
          <span style={{
            flexShrink: 0,
            width: 18, height: 18,
            borderRadius: 4,
            background: 'var(--accent-soft)', color: 'var(--accent-2)',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 10.5, fontWeight: 700,
            border: '1px solid var(--accent-border)',
          }}>{i + 1}</span>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 2 }}>
              <DocTypeBadge type={c.doc.type}/>
              <span style={{ fontSize: 12.5, fontWeight: 500, color: 'var(--fg)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {c.doc.title}
              </span>
              <span style={{ fontSize: 11, color: 'var(--fg-4)', flexShrink: 0 }}>{c.anchor}</span>
            </div>
            <div style={{ fontSize: 12, color: 'var(--fg-2)', lineHeight: 1.45, fontStyle: 'italic' }}>
              "{c.snippet}"
            </div>
          </div>
          <Icon name="external" size={13} style={{ color: 'var(--fg-4)', marginTop: 2, flexShrink: 0 }}/>
        </button>
      ))}
    </div>
  </div>
);

const SuggestedPrompts = () => {
  const prompts = [
    { icon: 'graph', label: 'Show the survivorship rules for postal addresses' },
    { icon: 'flow', label: 'Draft a stewardship task for this conflict' },
    { icon: 'database', label: 'Which downstream systems consume this record?' },
  ];
  return (
    <div style={{ padding: '4px 0 10px', display: 'flex', flexWrap: 'wrap', gap: 6 }}>
      {prompts.map(p => (
        <button key={p.label} style={{
          display: 'inline-flex', alignItems: 'center', gap: 6,
          padding: '6px 10px',
          background: 'var(--panel)',
          border: '1px solid var(--border)',
          borderRadius: 999,
          fontSize: 12.5, color: 'var(--fg-2)',
          fontWeight: 500,
          transition: 'border-color 120ms, background 120ms',
        }}
        onMouseEnter={e => { e.currentTarget.style.borderColor = 'var(--accent-border)'; e.currentTarget.style.background = 'var(--accent-soft)'; }}
        onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--border)'; e.currentTarget.style.background = 'var(--panel)'; }}
        >
          <Icon name={p.icon} size={13} style={{ color: 'var(--fg-3)' }}/>
          <span>{p.label}</span>
        </button>
      ))}
    </div>
  );
};

const Composer = () => {
  const [value, setValue] = React.useState('');
  const [scope, setScope] = React.useState('Customer domain');
  return (
    <div style={{ padding: '0 0 18px' }}>
      <SuggestedPrompts/>
      <div style={{
        background: 'var(--panel)',
        border: '1px solid var(--border)',
        borderRadius: 14,
        boxShadow: 'var(--shadow)',
        padding: '10px 12px 8px',
        transition: 'border-color 120ms',
      }}>
        <textarea
          value={value}
          onChange={e => setValue(e.target.value)}
          rows={2}
          placeholder="Ask about match rules, survivorship, lineage, stewardship SLAs…"
          style={{
            width: '100%', resize: 'none',
            border: 0, outline: 0, background: 'transparent',
            fontSize: 14, color: 'var(--fg)',
            lineHeight: 1.5, minHeight: 44,
            padding: 0,
          }}
        />
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 4 }}>
          <button style={chipBtn()}>
            <Icon name="paperclip" size={13}/>
            <span>Attach</span>
          </button>
          <button style={chipBtn(true)}>
            <Icon name="database" size={13}/>
            <span>{scope}</span>
            <Icon name="chevron-down" size={12}/>
          </button>
          <button style={chipBtn()}>
            <Icon name="filter" size={13}/>
            <span>Filters</span>
          </button>

          <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ fontSize: 11, color: 'var(--fg-4)' }} className="mono">
              {value.length} / 8,000
            </span>
            <button style={{
              width: 32, height: 32,
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
              background: value ? 'var(--fg)' : 'var(--bg-3)',
              color: value ? 'var(--bg)' : 'var(--fg-4)',
              border: 0, borderRadius: 8,
              transition: 'background 120ms',
            }}>
              <Icon name="arrow-up" size={15} stroke={2.2}/>
            </button>
          </div>
        </div>
      </div>
      <div style={{ textAlign: 'center', fontSize: 11, color: 'var(--fg-4)', marginTop: 8 }}>
        Sidecar fetches answers from your governed MDM corpus. Always verify before applying to production records.
      </div>
    </div>
  );
};

const chipBtn = (active) => ({
  display: 'inline-flex', alignItems: 'center', gap: 5,
  padding: '5px 9px',
  background: active ? 'var(--accent-soft)' : 'transparent',
  color: active ? 'var(--accent-2)' : 'var(--fg-2)',
  border: `1px solid ${active ? 'var(--accent-border)' : 'var(--border)'}`,
  borderRadius: 7,
  fontSize: 12, fontWeight: 500,
});

const ChatArea = ({ onOpenSource, onToggleInspector, inspectorOpen, onToggleSidebar, sidebarCollapsed }) => {
  const { activeMessages } = window.MDM_DATA;
  const scrollRef = React.useRef(null);

  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, background: 'var(--bg)' }}>
      <ChatHeader
        onToggleInspector={onToggleInspector}
        inspectorOpen={inspectorOpen}
        onToggleSidebar={onToggleSidebar}
        sidebarCollapsed={sidebarCollapsed}
      />
      <div ref={scrollRef} style={{
        flex: 1, overflowY: 'auto',
        display: 'flex', justifyContent: 'center',
      }}>
        <div style={{ width: '100%', maxWidth: 760, padding: '20px 24px 8px', display: 'flex', flexDirection: 'column' }}>
          {/* Day separator */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '4px 0 8px' }}>
            <div style={{ flex: 1, height: 1, background: 'var(--border)' }}/>
            <span style={{ fontSize: 11, color: 'var(--fg-4)', fontWeight: 500 }}>Today · May 23, 2026</span>
            <div style={{ flex: 1, height: 1, background: 'var(--border)' }}/>
          </div>
          {activeMessages.map(m => (
            m.role === 'user'
              ? <UserMessage key={m.id} m={m}/>
              : <AssistantMessage key={m.id} m={m} onOpenSource={onOpenSource}/>
          ))}
        </div>
      </div>
      <div style={{ display: 'flex', justifyContent: 'center', borderTop: '1px solid transparent', background: 'var(--bg)' }}>
        <div style={{ width: '100%', maxWidth: 760, padding: '0 24px' }}>
          <Composer/>
        </div>
      </div>
    </div>
  );
};

Object.assign(window, { ChatArea });
