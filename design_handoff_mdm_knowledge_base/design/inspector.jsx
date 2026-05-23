// Right-side source inspector

const Inspector = ({ openId, onClose }) => {
  const { sources } = window.MDM_DATA;
  const source = sources.find(s => s.id === openId) || sources[0];
  const [tab, setTab] = React.useState('source');

  return (
    <aside style={{
      width: 380, flexShrink: 0,
      borderLeft: '1px solid var(--border)',
      background: 'var(--bg-2)',
      display: 'flex', flexDirection: 'column',
      height: '100%',
    }}>
      {/* Header */}
      <div style={{ padding: '12px 14px', borderBottom: '1px solid var(--border)', background: 'var(--bg)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 10 }}>
          <Icon name="book" size={14} style={{ color: 'var(--fg-3)' }}/>
          <span style={{ fontSize: 11, fontWeight: 600, color: 'var(--fg-3)', textTransform: 'uppercase', letterSpacing: '0.07em' }}>
            Source inspector
          </span>
          <div style={{ marginLeft: 'auto' }}>
            <IconButton icon="panel" label="Close" onClick={onClose}/>
          </div>
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10 }}>
          <DocTypeBadge type={source.type}/>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14, fontWeight: 600, letterSpacing: '-0.01em', marginBottom: 4, lineHeight: 1.3 }}>
              {source.title}
            </div>
            <div style={{ fontSize: 11.5, color: 'var(--fg-3)' }}>
              {source.owner} · Updated {source.updated} · {source.pages} pages
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div style={{ display: 'flex', gap: 2, marginTop: 12, borderBottom: '1px solid var(--border)', marginLeft: -14, marginRight: -14, paddingLeft: 14, paddingRight: 14 }}>
          {[
            { id: 'source', label: 'Excerpt' },
            { id: 'lineage', label: 'Lineage' },
            { id: 'related', label: 'Related' },
          ].map(t => (
            <button key={t.id} onClick={() => setTab(t.id)} style={{
              padding: '8px 4px',
              marginRight: 14,
              marginBottom: -1,
              background: 'transparent', border: 0,
              borderBottom: tab === t.id ? '2px solid var(--fg)' : '2px solid transparent',
              color: tab === t.id ? 'var(--fg)' : 'var(--fg-3)',
              fontSize: 12.5, fontWeight: tab === t.id ? 600 : 500,
            }}>{t.label}</button>
          ))}
        </div>
      </div>

      {/* Content */}
      <div style={{ flex: 1, overflowY: 'auto', padding: '14px' }}>
        {tab === 'source' && <SourceExcerpt source={source}/>}
        {tab === 'lineage' && <LineagePanel/>}
        {tab === 'related' && <RelatedPanel/>}
      </div>
    </aside>
  );
};

const SourceExcerpt = ({ source }) => (
  <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
    {/* Tags */}
    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
      {source.tags.map(t => <Pill key={t} size="xs">{t}</Pill>)}
      <Pill size="xs" tone="ok" icon="shield">Approved</Pill>
    </div>

    {/* Snippet card */}
    <div style={{
      background: 'var(--panel)',
      border: '1px solid var(--border)',
      borderRadius: 10,
      overflow: 'hidden',
    }}>
      <div style={{
        padding: '8px 12px',
        borderBottom: '1px solid var(--border)',
        display: 'flex', alignItems: 'center', gap: 8,
        background: 'var(--bg-2)',
      }}>
        <span className="mono" style={{ fontSize: 10.5, fontWeight: 600, color: 'var(--fg-3)' }}>§ 4.2  TRUST HIERARCHY</span>
        <span style={{ marginLeft: 'auto', fontSize: 10.5, color: 'var(--fg-4)' }} className="mono">p. 14 / {source.pages}</span>
      </div>
      <div style={{ padding: '12px 14px', fontSize: 13, lineHeight: 1.6, color: 'var(--fg-2)' }}>
        <p style={{ margin: 0, marginBottom: 8 }}>
          For EMEA party records, <mark style={{ background: 'oklch(0.93 0.12 95)', color: 'inherit', padding: '1px 2px', borderRadius: 2 }}>SAP S/4 is ranked above Salesforce</mark> for postal address attributes with a base trust score of <span className="mono" style={{ background: 'var(--bg-3)', padding: '0 4px', borderRadius: 3, fontSize: 12 }}>92</span>.
        </p>
        <p style={{ margin: 0, marginBottom: 8 }}>
          When two candidate values fall within 10 trust points, the survivorship engine breaks the tie using the most recent <span className="mono" style={{ background: 'var(--bg-3)', padding: '0 4px', borderRadius: 3, fontSize: 12 }}>last_changed_at</span> timestamp from the contributing source.
        </p>
        <p style={{ margin: 0 }}>
          Records flagged by the Loqate address validator at <strong>confidence ≥ 4</strong> override lower-confidence values regardless of base trust, subject to Steward review for any change to a Tier-1 customer.
        </p>
      </div>
    </div>

    {/* Trust table preview */}
    <div>
      <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--fg-3)', textTransform: 'uppercase', letterSpacing: '0.07em', marginBottom: 8 }}>
        Trust scores · party.address.postal
      </div>
      <div style={{
        background: 'var(--panel)', border: '1px solid var(--border)', borderRadius: 10,
        overflow: 'hidden',
      }}>
        {[
          { sys: 'SAP S/4 HANA', region: 'EMEA', score: 92, bar: 0.92 },
          { sys: 'Salesforce', region: 'Global', score: 71, bar: 0.71 },
          { sys: 'NetSuite', region: 'NA', score: 64, bar: 0.64 },
          { sys: 'Marketing Cloud', region: 'Global', score: 38, bar: 0.38 },
        ].map((row, i) => (
          <div key={row.sys} style={{
            display: 'grid', gridTemplateColumns: '1fr auto',
            alignItems: 'center', gap: 12,
            padding: '8px 12px',
            borderBottom: i < 3 ? '1px solid var(--border)' : 0,
          }}>
            <div>
              <div style={{ fontSize: 12.5, fontWeight: 500, marginBottom: 4 }}>{row.sys}</div>
              <div style={{ height: 4, background: 'var(--bg-3)', borderRadius: 2, overflow: 'hidden' }}>
                <div style={{ width: `${row.bar * 100}%`, height: '100%', background: i === 0 ? 'var(--accent)' : 'oklch(0.70 0.04 250)' }}/>
              </div>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end' }}>
              <span className="mono" style={{ fontSize: 13, fontWeight: 600, color: 'var(--fg)' }}>{row.score}</span>
              <span style={{ fontSize: 10.5, color: 'var(--fg-4)' }}>{row.region}</span>
            </div>
          </div>
        ))}
      </div>
    </div>

    <button style={{
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6,
      padding: '8px 12px',
      background: 'var(--panel)', color: 'var(--fg)',
      border: '1px solid var(--border)', borderRadius: 8,
      fontSize: 13, fontWeight: 500,
    }}>
      <Icon name="external" size={14}/>
      <span>Open full document</span>
    </button>
  </div>
);

const LineagePanel = () => (
  <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
    <div style={{ fontSize: 12.5, color: 'var(--fg-2)' }}>
      Record <span className="mono" style={{ background: 'var(--bg-3)', padding: '1px 5px', borderRadius: 3 }}>CUST-490213</span> flows from these sources through the MDM hub to downstream systems.
    </div>

    <div style={{ background: 'var(--panel)', border: '1px solid var(--border)', borderRadius: 10, padding: 14 }}>
      <LineageNode label="SAP S/4 HANA" sub="System of record · EMEA" tone="primary"/>
      <LineageNode label="Salesforce CRM" sub="Operational · Global"/>
      <LineageEdge/>
      <LineageNode label="MDM Hub · Customer Domain" sub="Master · Reltio v2024.3" tone="accent" icon="sparkle"/>
      <LineageEdge/>
      <LineageNode label="Snowflake · MART_CUSTOMER" sub="Consumer · Analytics" small/>
      <LineageNode label="Marketing Cloud" sub="Consumer · Campaigns" small/>
      <LineageNode label="ServiceNow CSM" sub="Consumer · Support" small/>
    </div>
  </div>
);

const LineageNode = ({ label, sub, tone, icon, small }) => {
  const bg = tone === 'accent' ? 'var(--accent-soft)' : tone === 'primary' ? 'var(--bg-2)' : 'var(--bg)';
  const bd = tone === 'accent' ? 'var(--accent-border)' : 'var(--border)';
  return (
    <div style={{
      display: 'flex', alignItems: 'center', gap: 10,
      padding: small ? '8px 10px' : '10px 12px',
      background: bg, border: `1px solid ${bd}`, borderRadius: 8,
      marginBottom: small ? 6 : 0,
    }}>
      <div style={{
        width: 26, height: 26, borderRadius: 6,
        background: tone === 'accent' ? 'var(--accent)' : 'var(--bg-3)',
        color: tone === 'accent' ? 'white' : 'var(--fg-3)',
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      }}>
        <Icon name={icon || 'database'} size={14} stroke={1.8}/>
      </div>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 12.5, fontWeight: 500 }}>{label}</div>
        <div style={{ fontSize: 11, color: 'var(--fg-3)' }}>{sub}</div>
      </div>
    </div>
  );
};

const LineageEdge = () => (
  <div style={{ marginLeft: 12, height: 14, display: 'flex', alignItems: 'center' }}>
    <div style={{ width: 2, height: '100%', background: 'var(--border-strong)' }}/>
  </div>
);

const RelatedPanel = () => {
  const { sources } = window.MDM_DATA;
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      {sources.slice(1, 5).map(s => (
        <button key={s.id} style={{
          display: 'flex', alignItems: 'flex-start', gap: 10,
          padding: '10px 12px',
          background: 'var(--panel)', border: '1px solid var(--border)', borderRadius: 8,
          textAlign: 'left',
        }}>
          <DocTypeBadge type={s.type}/>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 12.5, fontWeight: 500, marginBottom: 2 }}>{s.title}</div>
            <div style={{ fontSize: 11, color: 'var(--fg-3)' }}>{s.owner} · {s.updated}</div>
          </div>
        </button>
      ))}
    </div>
  );
};

Object.assign(window, { Inspector });
