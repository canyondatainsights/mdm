// Mock data for MDM Knowledge Base
const conversations = [
  { id: 'c1', title: 'Customer golden record reconciliation', date: 'Today', preview: 'How do we handle conflicting addresses across SAP and Salesforce…', pinned: true },
  { id: 'c2', title: 'Match rules for B2B accounts', date: 'Today', preview: 'Current threshold is 0.82 — should we tune by region?', pinned: true },
  { id: 'c3', title: 'Survivorship for product hierarchy', date: 'Yesterday', preview: 'Source ranking for the EMEA catalog migration.' },
  { id: 'c4', title: 'Stewardship queue SLA', date: 'Yesterday', preview: 'Open tasks over 72h trending up in Q2.' },
  { id: 'c5', title: 'DUNS enrichment vs. Dun & Bradstreet', date: '2 days ago', preview: 'Diff between live API and last nightly batch.' },
  { id: 'c6', title: 'GDPR right-to-erasure workflow', date: '3 days ago', preview: 'Which downstream systems need confirmation?' },
  { id: 'c7', title: 'Reltio → Informatica migration', date: 'May 16', preview: 'Mapping document for the party model.' },
  { id: 'c8', title: 'Cost center hierarchy delta', date: 'May 14', preview: 'New nodes from Workday — propagate to which spokes?' },
  { id: 'c9', title: 'Vendor onboarding match score', date: 'May 12', preview: 'Reviewing 38 medium-confidence matches.' },
  { id: 'c10', title: 'Address standardization library', date: 'May 09', preview: 'Loqate vs. Melissa — output diff on APAC sample.' },
];

const sources = [
  { id: 's1', title: 'Customer Master Data Governance Policy', type: 'PDF', pages: 42, owner: 'Data Governance Council', updated: 'May 18, 2026', tags: ['policy', 'customer'] },
  { id: 's2', title: 'Match & Merge Rules — v3.4', type: 'Confluence', pages: 18, owner: 'MDM Platform Team', updated: 'May 22, 2026', tags: ['runbook', 'rules'] },
  { id: 's3', title: 'Survivorship Configuration Guide', type: 'PDF', pages: 27, owner: 'D. Okafor', updated: 'May 11, 2026', tags: ['config'] },
  { id: 's4', title: 'Party Model — Logical Data Model', type: 'PPTX', pages: 64, owner: 'Enterprise Architecture', updated: 'Apr 30, 2026', tags: ['model'] },
  { id: 's5', title: 'Stewardship SOP — Tier 1 Tasks', type: 'DOCX', pages: 12, owner: 'M. Chen', updated: 'May 20, 2026', tags: ['sop'] },
  { id: 's6', title: 'Source System Trust Hierarchy', type: 'XLSX', pages: 6, owner: 'MDM Platform Team', updated: 'May 02, 2026', tags: ['config'] },
];

const activeMessages = [
  {
    id: 'm1',
    role: 'user',
    content: 'We are seeing conflicting addresses for customer ID CUST-490213 across SAP S/4 and Salesforce. SAP has a Düsseldorf address from last week; Salesforce still shows Cologne from 2024. Which one should win in the golden record, and how do we prevent the next overwrite?',
    time: '10:42'
  },
  {
    id: 'm2',
    role: 'assistant',
    time: '10:42',
    content: [
      { type: 'p', text: 'For CUST-490213, the **Düsseldorf address from SAP S/4** should survive in the golden record. Three rules drive this:' },
      { type: 'ol', items: [
        'Source trust — **SAP S/4 ranks above Salesforce** for the `party.address.postal` attribute group in EMEA. Trust score 92 vs. 71.',
        'Recency tie-breaker — when sources are within 10 points of trust, the **most recent change wins**. SAP\'s record is 6 days old vs. 412 days for Salesforce.',
        'Validation — SAP\'s payload passed Loqate Verify with a 5/5 confidence, Salesforce\'s did not re-validate after the 2024 import.'
      ]},
      { type: 'p', text: 'To prevent the next overwrite, you have two options on the Salesforce side:' },
      { type: 'callout', kind: 'tip', text: 'Option A is preferred. It avoids back-and-forth flapping in the stewardship queue.' },
      { type: 'options', items: [
        { label: 'A — Switch the Salesforce party.address sync to read-only', body: 'Set field-level security on Account.BillingAddress to read-only for the Integration User, and let the MDM hub push canonical values nightly.' },
        { label: 'B — Add an SLA-based suppression rule', body: 'Allow writes from Salesforce only when SAP has not modified the field within the last 14 days. Implemented as a pre-merge rule in the Match & Merge config.' }
      ]},
      { type: 'p', text: 'I drafted the rule change for Option A — want me to open a ticket against the MDM Platform Jira project?' }
    ],
    citations: [
      { id: 's2', anchor: '§4.2 Trust Hierarchy', snippet: 'For EMEA party records, SAP S/4 is ranked above Salesforce for postal address attributes with a base trust of 92.' },
      { id: 's6', anchor: 'Row 14', snippet: 'party.address.postal — SAP_S4: 92, SFDC: 71, NetSuite: 64.' },
      { id: 's3', anchor: '§3.1 Recency Tie-Breaker', snippet: 'When two candidate values are within 10 trust points, the most recently changed value survives.' }
    ],
    confidence: 'high',
    sources_used: 3
  }
];

const stats = {
  documents: 1247,
  conversations: 3812,
  records_governed: '4.2M',
  pending_reviews: 38,
};

Object.assign(window, { MDM_DATA: { conversations, sources, activeMessages, stats } });
