/**
 * Mock data for the console. No backend - every view reads from here so the
 * numbers stay consistent across pages. Swap these for real API calls later.
 */

export const SENTIMENTS = [
  'interested',
  'question',
  'not_now',
  'unsubscribe',
  'wrong_person',
  'auto_reply',
]

export const TASK_STATUSES = [
  'open',
  'in_progress',
  'waiting_customer',
  'resolved',
  'dismissed',
]

export const tenants = [
  { id: 42, name: 'Northshore Windows (CA)', plan: 'growth' },
  { id: 43, name: 'Lakeside Doors (CA)', plan: 'starter' },
]

export const managers = [
  { id: 1, name: 'Olena K.', email: 'olena@northshore.example', role: 'Remarketing Lead' },
  { id: 2, name: 'Marco P.', email: 'marco@northshore.example', role: 'Sales Manager' },
  { id: 3, name: 'Ivana R.', email: 'ivana@northshore.example', role: 'Sales Manager' },
]

export const clients = [
  { id: 1, tenant_id: 42, name: 'Dana Walker', email: 'd.walker@northshore-homes.ca', step: 2, suppressed_at: null },
  { id: 2, tenant_id: 42, name: 'Marc Tremblay', email: 'm.tremblay@lakesideprop.ca', step: 1, suppressed_at: null },
  { id: 3, tenant_id: 42, name: 'Rita Osei', email: 'r.osei@maplecourt.ca', step: 3, suppressed_at: '2026-09-09T14:12:00Z' },
  { id: 4, tenant_id: 42, name: 'Jan Kowalczyk', email: 'j.kowalczyk@bridgeportbuild.ca', step: 2, suppressed_at: null },
  { id: 5, tenant_id: 42, name: 'Sofia Lindqvist', email: 's.lindqvist@harbourview.ca', step: 1, suppressed_at: null },
  { id: 6, tenant_id: 42, name: 'Ana Ferreira', email: 'a.ferreira@stonegate.ca', step: 2, suppressed_at: null },
  { id: 7, tenant_id: 42, name: 'Luc Beaulieu', email: 'l.beaulieu@ridgetop.ca', step: 3, suppressed_at: null },
  { id: 8, tenant_id: 42, name: 'Kwame Mensah', email: 'k.mensah@fairlawn.ca', step: 1, suppressed_at: '2026-09-03T09:40:00Z' },
]

export const campaigns = [
  {
    id: 7,
    name: 'Dormant leads · window quotes',
    status: 'active',
    steps: 4,
    cadence_days: 3,
    enrolled: 128,
    active: 74,
    stopped: 41,
    completed: 13,
    reply_rate: 0.22,
  },
  {
    id: 9,
    name: 'Expired quotes · Q3 win-back',
    status: 'active',
    steps: 3,
    cadence_days: 5,
    enrolled: 61,
    active: 38,
    stopped: 17,
    completed: 6,
    reply_rate: 0.18,
  },
  {
    id: 11,
    name: 'Cold list · spring 2026',
    status: 'paused',
    steps: 5,
    cadence_days: 4,
    enrolled: 240,
    active: 0,
    stopped: 208,
    completed: 32,
    reply_rate: 0.09,
  },
]

const bodies = {
  interested: 'Sounds good. Can you call me Thursday afternoon? I still have the quote.',
  question: 'How much would it be for eight windows on the second floor?',
  not_now: 'Not this year, our renovation budget is spent. Maybe spring.',
  unsubscribe: 'Please take me off your list, I do not want any more emails.',
  wrong_person: 'You have the wrong person, I never requested a quote.',
  auto_reply: 'I am out of office until Monday with limited access to email.',
}

function daysAgo(d, h = 0) {
  const t = new Date('2026-09-10T11:00:00Z')
  t.setDate(t.getDate() - d)
  t.setHours(t.getHours() - h)
  return t.toISOString()
}

export const replyTasks = [
  { id: 1, tenant_id: 42, client_id: 1, campaign_id: 7, step: 2, event_id: 'evt_01HZ8A0001', sentiment: 'interested', status: 'in_progress', assignee_id: 2, created_at: daysAgo(0, 2), body: bodies.interested },
  { id: 2, tenant_id: 42, client_id: 2, campaign_id: 7, step: 1, event_id: 'evt_01HZ8A0002', sentiment: 'question', status: 'open', assignee_id: null, created_at: daysAgo(0, 4), body: bodies.question },
  { id: 3, tenant_id: 42, client_id: 3, campaign_id: 7, step: 3, event_id: 'evt_01HZ8A0003', sentiment: 'unsubscribe', status: 'resolved', assignee_id: 1, created_at: daysAgo(1, 1), body: bodies.unsubscribe },
  { id: 4, tenant_id: 42, client_id: 4, campaign_id: 9, step: 2, event_id: 'evt_01HZ8A0004', sentiment: null, status: 'open', assignee_id: null, created_at: daysAgo(1, 3), body: 'Thanks — will discuss internally and revert.' },
  { id: 5, tenant_id: 42, client_id: 5, campaign_id: 7, step: 1, event_id: 'evt_01HZ8A0005', sentiment: 'auto_reply', status: 'dismissed', assignee_id: 3, created_at: daysAgo(2, 0), body: bodies.auto_reply },
  { id: 6, tenant_id: 42, client_id: 6, campaign_id: 9, step: 2, event_id: 'evt_01HZ8A0006', sentiment: 'not_now', status: 'waiting_customer', assignee_id: 3, created_at: daysAgo(2, 5), body: bodies.not_now },
  { id: 7, tenant_id: 42, client_id: 7, campaign_id: 7, step: 3, event_id: 'evt_01HZ8A0007', sentiment: 'wrong_person', status: 'resolved', assignee_id: 1, created_at: daysAgo(3, 2), body: bodies.wrong_person },
  { id: 8, tenant_id: 42, client_id: 8, campaign_id: 7, step: 1, event_id: 'evt_01HZ8A0012', sentiment: null, status: 'open', assignee_id: null, created_at: daysAgo(3, 6), body: 'Could you resend the brochure? The link expired.' },
]

export const outboxEvents = replyTasks
  .filter((t) => t.status !== 'dismissed')
  .map((t, i) => ({
    id: i + 1,
    subject: 'events.reply.processed',
    dedupe_key: t.event_id,
    status: i % 7 === 3 ? 'pending' : 'published',
    attempts: i % 7 === 3 ? 2 : 1,
    payload: {
      event_id: t.event_id,
      tenant_id: t.tenant_id,
      client_id: t.client_id,
      reply_task_id: t.id,
      sentiment: t.sentiment,
      campaign_stopped: t.sentiment !== 'auto_reply',
      client_suppressed: ['unsubscribe', 'wrong_person'].includes(t.sentiment),
    },
    published_at: i % 7 === 3 ? null : t.created_at,
  }))

export const busEvents = [
  { seq: 62, type: 'reply.received', tenant_id: 42, client_id: 1, ts: daysAgo(0, 2), payload: { event_id: 'evt_01HZ8A0001', sender: 'd.walker@northshore-homes.ca', body_plain: bodies.interested, headers: { 'In-Reply-To': '<step2.c42@mg>' } } },
  { seq: 63, type: 'reply.classified', tenant_id: 42, client_id: 1, ts: daysAgo(0, 2), payload: { event_id: 'evt_01HZ8A0001', sentiment: 'interested', model: 'gpt-4o-mini', prompt_version: 'v7', latency_ms: 812 } },
  { seq: 64, type: 'reply_task.created', tenant_id: 42, client_id: 1, ts: daysAgo(0, 2), payload: { reply_task_id: 1, sentiment: 'interested', status: 'open' } },
  { seq: 65, type: 'campaign.stopped', tenant_id: 42, client_id: 1, ts: daysAgo(0, 2), payload: { campaign_id: 7, reason: 'inbound_reply' } },
  { seq: 66, type: 'events.reply.processed', tenant_id: 42, client_id: 1, ts: daysAgo(0, 2), payload: { event_id: 'evt_01HZ8A0001', reply_task_id: 1, sentiment: 'interested' } },
  { seq: 67, type: 'reply.received', tenant_id: 42, client_id: 3, ts: daysAgo(1, 1), payload: { event_id: 'evt_01HZ8A0003', sender: 'r.osei@maplecourt.ca', body_plain: bodies.unsubscribe } },
  { seq: 68, type: 'reply.classified', tenant_id: 42, client_id: 3, ts: daysAgo(1, 1), payload: { event_id: 'evt_01HZ8A0003', sentiment: 'unsubscribe', model: 'rule', prompt_version: 'v7', latency_ms: 3 } },
  { seq: 69, type: 'client.suppressed', tenant_id: 42, client_id: 3, ts: daysAgo(1, 1), payload: { source: 'reply_pipeline', reason: 'unsubscribe' } },
  { seq: 70, type: 'crm.suppression.synced', tenant_id: 42, client_id: 3, ts: daysAgo(1, 0), payload: { crm_id: 'CRM-88231', direction: 'push', ok: true } },
  { seq: 71, type: 'classifier.failed', tenant_id: 42, client_id: 4, ts: daysAgo(1, 3), payload: { event_id: 'evt_01HZ8A0004', error: 'invalid_json', attempt: 2, fallback: 'sentiment=null' } },
  { seq: 72, type: 'reply_task.status_changed', tenant_id: 42, client_id: 1, ts: daysAgo(0, 1), payload: { reply_task_id: 1, from: 'open', to: 'in_progress', by: 'Marco P.' } },
  { seq: 73, type: 'flow.handoff', tenant_id: 42, client_id: 6, ts: daysAgo(0, 3), payload: { from_flow: 'winback_q3', to_flow: 'nurture_longterm', by: 'Ivana R.' } },
]

// Raw inbound `reply.received` requests as they land on the bus from the mail
// gateway (mirrors tests/Fixtures/inbound_events.json). This is the "before"
// side — busEvents above is what the pipeline derives from these.
export const inboundRequests = [
  { event_id: 'evt_01HZ8A0001', tenant_id: 42, sender: 'd.walker@northshore-homes.ca', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: 'Sounds good. Can you call me Thursday afternoon? I still have the quote.', headers: { 'Message-Id': '<20260901.1001@mail.northshore-homes.ca>', 'In-Reply-To': '<step2.c42@mg.ourdomain.com>', 'Auto-Submitted': 'no' }, timestamp: 1756713600 },
  { event_id: 'evt_01HZ8A0002', tenant_id: 42, sender: 'm.tremblay@lakesideprop.ca', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: 'How much would it be for eight windows on the second floor?', headers: { 'Message-Id': '<20260901.1002@mail.lakesideprop.ca>', 'In-Reply-To': '<step1.c42@mg.ourdomain.com>', 'Auto-Submitted': 'no' }, timestamp: 1756717200 },
  { event_id: 'evt_01HZ8A0003', tenant_id: 42, sender: 'r.osei@maplecourt.ca', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: 'Please take me off your list. I do not want any more emails about this.', headers: { 'Message-Id': '<20260901.1003@mail.maplecourt.ca>', 'In-Reply-To': '<step3.c42@mg.ourdomain.com>', 'Auto-Submitted': 'no' }, timestamp: 1756720800 },
  { event_id: 'evt_01HZ8A0004', tenant_id: 42, sender: 'j.kowalczyk@bridgeportbuild.ca', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: 'I am out of the office until September 15 with limited access to email. For urgent matters please contact reception at 604-555-0142.', headers: { 'Message-Id': '<20260901.1004@mail.bridgeportbuild.ca>', 'In-Reply-To': '<step2.c42@mg.ourdomain.com>', 'Auto-Submitted': 'auto-replied', 'X-Auto-Response-Suppress': 'All' }, timestamp: 1756724400 },
  { event_id: 'evt_01HZ8A0005', tenant_id: 42, sender: 's.lindqvist@harbourview.ca', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: 'Automatic reply: I am on parental leave until March. Your message has not been forwarded.', headers: { 'Message-Id': '<20260902.1005@mail.harbourview.ca>', 'In-Reply-To': '<step1.c42@mg.ourdomain.com>', 'Auto-Submitted': 'auto-replied' }, timestamp: 1756800000 },
  { event_id: 'evt_01HZ8A0006', tenant_id: 42, sender: 'a.ferreira@stonegate.ca', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: 'Thank you for your message. Our office is closed for the long weekend and will reopen Tuesday.', headers: { 'Message-Id': '<20260902.1006@mail.stonegate.ca>', 'In-Reply-To': '<step2.c42@mg.ourdomain.com>', 'X-Autoreply': 'yes', Precedence: 'bulk' }, timestamp: 1756803600 },
  { event_id: 'evt_01HZ8A0007', tenant_id: 42, sender: 'MAILER-DAEMON@mg.ourdomain.com', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: "This is the mail system at host mg.ourdomain.com. I'm sorry to have to inform you that your message could not be delivered. 550 5.1.1 <p.novak@cedarline.ca>: Recipient address rejected: User unknown in virtual mailbox table", headers: { 'Message-Id': '<20260902.1007@mg.ourdomain.com>', 'Content-Type': 'multipart/report; report-type=delivery-status', 'Auto-Submitted': 'auto-generated' }, timestamp: 1756807200 },
  { event_id: 'evt_01HZ8A0008', tenant_id: 42, sender: 'l.beaulieu@ridgetop.ca', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: '', body_html: '<html><body><div>Not this year, our budget is spent. Try us again in the spring.</div></body></html>', headers: { 'Message-Id': '<20260903.1008@mail.ridgetop.ca>', 'In-Reply-To': '<step3.c42@mg.ourdomain.com>', 'Auto-Submitted': 'no' }, timestamp: 1756890000 },
  { event_id: 'evt_01HZ8A0009', tenant_id: 42, sender: 'campaign+c42@mg.ourdomain.com', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: 'Hi there, just checking in on the quote we sent over.', headers: { 'Message-Id': '<20260903.1009@mg.ourdomain.com>', 'In-Reply-To': '<step2.c42@mg.ourdomain.com>', 'Auto-Submitted': 'no' }, timestamp: 1756893600 },
  { event_id: 'evt_01HZ8A0001', tenant_id: 42, sender: 'd.walker@northshore-homes.ca', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: 'Sounds good. Can you call me Thursday afternoon? I still have the quote.', headers: { 'Message-Id': '<20260901.1001@mail.northshore-homes.ca>', 'In-Reply-To': '<step2.c42@mg.ourdomain.com>', 'Auto-Submitted': 'no' }, timestamp: 1756713600 },
  { event_id: 'evt_01HZ8A0011', tenant_id: 43, sender: 'd.walker@northshore-homes.ca', recipient: 'campaign+c43@mg.ourdomain.com', body_plain: 'Yes, we are interested. Who should I talk to about installation dates?', headers: { 'Message-Id': '<20260904.1011@mail.northshore-homes.ca>', 'In-Reply-To': '<step1.c43@mg.ourdomain.com>', 'Auto-Submitted': 'no' }, timestamp: 1756980000 },
  { event_id: 'evt_01HZ8A0012', tenant_id: 42, sender: 'k.mensah@fairlawn.ca', recipient: 'campaign+c42@mg.ourdomain.com', body_plain: 'Actually, could you send the brochure again? The link expired.', headers: { 'Message-Id': '<20260828.1012@mail.fairlawn.ca>', 'In-Reply-To': '<step1.c42@mg.ourdomain.com>', 'Auto-Submitted': 'no' }, timestamp: 1756368000 },
]

export const promptVersions = [
  {
    version: 'v7',
    status: 'active',
    author: 'Olena K.',
    updated_at: daysAgo(4),
    note: 'Tightened wrong_person vs not_now boundary; added few-shot for auto_reply.',
    accuracy: 0.93,
    body: `You classify a single customer email reply into exactly one label.

Labels: interested, question, not_now, unsubscribe, wrong_person, auto_reply

Rules:
- "unsubscribe" only for an explicit opt-out request.
- "wrong_person" when the sender says they are not the intended recipient.
- "auto_reply" for out-of-office / vacation autoresponders.
- "question" when the reply asks for information and shows buying intent.
- Reply as JSON: {"sentiment": "<label>"} and nothing else.`,
    // free-form reasoning guidance injected into the model request (system-side,
    // never returned to the caller)
    reasoning: `Work through it silently before answering:
1. Is there an explicit opt-out phrase? -> unsubscribe
2. Does the sender say they are not the right person / never asked? -> wrong_person
3. Is this an automated out-of-office / vacation responder? -> auto_reply
4. Otherwise weigh buying intent: a concrete question or scheduling -> question / interested,
   a soft deferral -> not_now.
Do not include this reasoning in the output.`,
  },
  {
    version: 'v6',
    status: 'archived',
    author: 'Olena K.',
    updated_at: daysAgo(21),
    note: 'Baseline rubric prompt.',
    accuracy: 0.88,
    body: 'Classify the reply into one of: interested, question, not_now, unsubscribe, wrong_person, auto_reply. Return JSON {"sentiment": "..."}.',
    reasoning: '',
  },
  {
    version: 'v8-draft',
    status: 'draft',
    author: 'Marco P.',
    updated_at: daysAgo(0, 6),
    note: 'Trial: add confidence score + short rationale for the Reply Center UI.',
    accuracy: null,
    body: `Same rubric as v7. Additionally return "confidence" (0-1) and a one-sentence "rationale".
Output: {"sentiment": "<label>", "confidence": <number>, "rationale": "<text>"}`,
    reasoning: `Same silent checklist as v7. Additionally: after deciding, write one short
sentence that a manager could read to understand why. Keep it under 20 words.`,
  },
]

export const suppression = [
  { id: 1, client: 'Rita Osei', email: 'r.osei@maplecourt.ca', source: 'reply_pipeline', reason: 'unsubscribe', added_at: daysAgo(1), crm_synced: true },
  { id: 2, client: 'Kwame Mensah', email: 'k.mensah@fairlawn.ca', source: 'support_desk', reason: 'manual removal request', added_at: daysAgo(7), crm_synced: true },
  { id: 3, client: 'P. Nguyen', email: 'p.nguyen@example.ca', source: 'crm_connector', reason: 'crm blacklist', added_at: daysAgo(2), crm_synced: true },
  { id: 4, client: 'H. Okafor', email: 'h.okafor@example.ca', source: 'reply_pipeline', reason: 'wrong_person', added_at: daysAgo(0, 5), crm_synced: false },
]

// --- CRM blacklist / suppression sync -------------------------------------
export const crmSync = {
  enabled: true,
  direction: 'bidirectional', // push | pull | bidirectional
  schedule: 'every 15 min',
  conflict_policy: 'suppress_wins', // suppress_wins | crm_wins | newest_wins
  last_run_at: daysAgo(0, 0),
  next_run_at: daysAgo(0, -1),
  endpoint: 'https://crm.internal/api/v2/suppressions',
  in_sync: 1042,
  pending_push: 3,
  pending_pull: 1,
  failed: 2,
  field_map: [
    { local: 'clients.email', crm: 'contact.email', key: true },
    { local: 'clients.suppressed_at', crm: 'contact.do_not_contact_at' },
    { local: 'suppression.reason', crm: 'contact.suppression_reason' },
    { local: 'suppression.source', crm: 'contact.suppression_origin' },
  ],
}

export const crmSyncQueue = [
  { id: 1, email: 'h.okafor@example.ca', direction: 'push', reason: 'wrong_person', origin: 'reply_pipeline', status: 'pending', attempts: 0, last_error: null, queued_at: daysAgo(0, 5) },
  { id: 2, email: 'j.park@example.ca', direction: 'push', reason: 'unsubscribe', origin: 'reply_pipeline', status: 'pending', attempts: 0, last_error: null, queued_at: daysAgo(0, 2) },
  { id: 3, email: 'a.silva@example.ca', direction: 'push', reason: 'manual removal request', origin: 'support_desk', status: 'pending', attempts: 0, last_error: null, queued_at: daysAgo(0, 1) },
  { id: 4, email: 'l.romano@example.ca', direction: 'pull', reason: 'crm blacklist', origin: 'crm_connector', status: 'pending', attempts: 0, last_error: null, queued_at: daysAgo(0, 3) },
  { id: 5, email: 'm.cohen@example.ca', direction: 'push', reason: 'unsubscribe', origin: 'reply_pipeline', status: 'failed', attempts: 4, last_error: 'CRM 409: contact locked by another process', queued_at: daysAgo(1, 2) },
  { id: 6, email: 'invalid@@example', direction: 'push', reason: 'unsubscribe', origin: 'reply_pipeline', status: 'failed', attempts: 2, last_error: 'CRM 422: invalid email format', queued_at: daysAgo(0, 8) },
]

export const crmSyncRuns = [
  { id: 148, started_at: daysAgo(0, 0), direction: 'bidirectional', pushed: 5, pulled: 2, conflicts: 0, duration_ms: 1840, result: 'ok' },
  { id: 147, started_at: daysAgo(0, 1), direction: 'bidirectional', pushed: 0, pulled: 1, conflicts: 1, duration_ms: 1210, result: 'ok' },
  { id: 146, started_at: daysAgo(0, 2), direction: 'bidirectional', pushed: 2, pulled: 0, conflicts: 0, duration_ms: 990, result: 'partial', note: '1 row failed → queue' },
  { id: 145, started_at: daysAgo(0, 3), direction: 'push', pushed: 7, pulled: 0, conflicts: 0, duration_ms: 2510, result: 'ok' },
  { id: 144, started_at: daysAgo(0, 5), direction: 'bidirectional', pushed: 0, pulled: 0, conflicts: 0, duration_ms: 30040, result: 'failed', note: 'CRM timeout' },
]

export const flows = [
  { id: 'winback_q3', name: 'Q3 win-back', clients: 38, avg_days: 12 },
  { id: 'nurture_longterm', name: 'Long-term nurture', clients: 91, avg_days: 45 },
  { id: 'hot_handoff', name: 'Hot → Sales handoff', clients: 7, avg_days: 1 },
]

export const handoffs = [
  { id: 1, client: 'Ana Ferreira', from: 'winback_q3', to: 'nurture_longterm', by: 'Ivana R.', at: daysAgo(0, 3), reason: 'not_now → revisit in spring' },
  { id: 2, client: 'Dana Walker', from: 'winback_q3', to: 'hot_handoff', by: 'Marco P.', at: daysAgo(0, 2), reason: 'interested → book a call' },
  { id: 3, client: 'Luc Beaulieu', from: 'winback_q3', to: 'nurture_longterm', by: 'system', at: daysAgo(3, 0), reason: 'no engagement after 3 steps' },
]

export const funnel = [
  { stage: 'Enrolled', count: 429 },
  { stage: 'Delivered', count: 401 },
  { stage: 'Replied', count: 92 },
  { stage: 'Reply task created', count: 88 },
  { stage: 'Qualified by manager', count: 37 },
  { stage: 'Quote / contract sent', count: 21 },
  { stage: 'Contract signed', count: 9 },
]


export const fieldTeams = [
  { id: 1, team: 'GTA North', members: 4, open_appointments: 6, region: 'Toronto North' },
  { id: 2, team: 'GTA West', members: 3, open_appointments: 4, region: 'Mississauga / Oakville' },
  { id: 3, team: 'Ottawa', members: 2, open_appointments: 2, region: 'Ottawa' },
]

export const appointments = [
  { id: 1, client: 'Dana Walker', team: 'GTA North', when: daysAgo(-2, 0), type: 'On-site measure', source: 'reply_task #1 (interested)' },
  { id: 2, client: 'Jan Kowalczyk', team: 'GTA West', when: daysAgo(-4, 0), type: 'Quote review', source: 'reply_task #4' },
]

export const FIELD_SKILLS = [
  'Window Measurement',
  'Door Measurement',
  'Consultation',
  'Quote Review',
]

export const FIELD_SERVICES = [
  { value: 'measurement', label: 'Measurement' },
  { value: 'consultation', label: 'Consultation' },
  { value: 'quote_review', label: 'Quote review' },
  { value: 'site_visit', label: 'Site visit' },
]

export const FIELD_KIT = ['Laser measure', 'Ladder', 'Samples', 'Tablet']

export const fieldServiceTypes = [
  { key: 'measurement', label: 'On-site measure', duration_min: 60, travel_buffer_min: 30, required_skill: 'Window Measurement', confirm: true, fee: 75 },
  { key: 'consultation', label: 'Consultation', duration_min: 45, travel_buffer_min: 30, required_skill: 'Consultation', confirm: true, fee: 0 },
  { key: 'quote_review', label: 'Quote review', duration_min: 45, travel_buffer_min: 20, required_skill: 'Quote Review', confirm: true, fee: 0 },
  { key: 'site_visit', label: 'Site visit', duration_min: 40, travel_buffer_min: 30, required_skill: 'Consultation', confirm: false, fee: 0 },
]

export const fieldDispatchStrategies = [
  { value: 'nearest', label: 'Nearest available' },
  { value: 'least_busy', label: 'Least busy' },
  { value: 'best_skill', label: 'Best skill match' },
  { value: 'manual', label: 'Manual assignment' },
]

export const fieldHolidays = [
  { date: '2026-09-07', name: 'Labour Day' },
  { date: '2026-10-12', name: 'Thanksgiving' },
  { date: '2026-12-25', name: 'Christmas Day' },
  { date: '2026-12-26', name: 'Boxing Day' },
]

export function defaultFieldHours() {
  return [
    { day: 'Monday', start: '08:00', end: '18:00', off: false },
    { day: 'Tuesday', start: '08:00', end: '18:00', off: false },
    { day: 'Wednesday', start: '08:00', end: '18:00', off: false },
    { day: 'Thursday', start: '08:00', end: '18:00', off: false },
    { day: 'Friday', start: '08:00', end: '16:00', off: false },
    { day: 'Saturday', start: '09:00', end: '14:00', off: true },
    { day: 'Sunday', start: '09:00', end: '14:00', off: true },
  ]
}

export const fieldTeamExtras = {
  1: {
    services: ['measurement', 'consultation', 'quote_review'],
    skills: ['Window Measurement', 'Door Measurement', 'Consultation'],
    cities: ['North York', 'Toronto', 'Vaughan'],
    postal_prefixes: 'M2N, M5M, M6C',
    hours: defaultFieldHours(),
    lunch_start: '12:00',
    lunch_end: '13:00',
    kit: ['Laser measure', 'Ladder', 'Samples'],
    dispatch: 'nearest',
    confirm_customer: true,
    remind_24h: true,
    remind_2h: true,
    notify_email: true,
    notify_sms: true,
    arrival_window_min: 60,
  },
  2: {
    services: ['measurement', 'consultation', 'quote_review'],
    skills: ['Window Measurement', 'Quote Review', 'Consultation'],
    cities: ['Mississauga', 'Oakville', 'Etobicoke'],
    postal_prefixes: 'L5H, L6J, M8Y',
    hours: defaultFieldHours(),
    lunch_start: '12:00',
    lunch_end: '12:45',
    kit: ['Laser measure', 'Ladder', 'Tablet'],
    dispatch: 'least_busy',
    confirm_customer: true,
    remind_24h: true,
    remind_2h: false,
    notify_email: true,
    notify_sms: true,
    arrival_window_min: 45,
  },
  3: {
    services: ['measurement', 'consultation'],
    skills: ['Window Measurement', 'Consultation'],
    cities: ['Ottawa'],
    postal_prefixes: 'K1P, K1H',
    hours: defaultFieldHours(),
    lunch_start: '12:00',
    lunch_end: '13:00',
    kit: ['Laser measure', 'Samples'],
    dispatch: 'best_skill',
    confirm_customer: true,
    remind_24h: true,
    remind_2h: true,
    notify_email: true,
    notify_sms: false,
    arrival_window_min: 60,
  },
}

export const fieldCalendarVisits = [
  { id: 'm1', team_id: 1, team: 'GTA North', client: 'Dana Walker', scheduled_at: daysAgo(-2, -3), type: 'measurement', status: 'scheduled', duration_min: 60, arrival_window: '13:00–14:00' },
  { id: 'm2', team_id: 1, team: 'GTA North', client: 'Rita Osei', scheduled_at: daysAgo(-2, -5), type: 'consultation', status: 'scheduled', duration_min: 45, arrival_window: '10:00–11:00' },
  { id: 'm3', team_id: 2, team: 'GTA West', client: 'Ana Ferreira', scheduled_at: daysAgo(-5, -2), type: 'measurement', status: 'confirmed', duration_min: 60, arrival_window: '09:00–10:00' },
  { id: 'm4', team_id: 3, team: 'Ottawa', client: 'Sofia Lindqvist', scheduled_at: daysAgo(-3, -4), type: 'site_visit', status: 'scheduled', duration_min: 40, arrival_window: '14:00–15:00' },
  { id: 'm5', team_id: 1, team: 'GTA North', client: 'Kwame Mensah', scheduled_at: daysAgo(1, 2), type: 'quote_review', status: 'completed', duration_min: 45, arrival_window: '11:00–12:00' },
]

export const kpis = {
  replies_today: 11,
  open_tasks: 4,
  active_enrollments: 112,
  suppression_events_7d: 6,
  classifier_fail_rate_7d: 0.021,
  conversion_rate: 0.021,
}

export const repliesByDay = [6, 9, 4, 12, 8, 15, 11]
export const sentimentMix = [
  { label: 'interested', value: 18 },
  { label: 'question', value: 24 },
  { label: 'not_now', value: 15 },
  { label: 'unsubscribe', value: 9 },
  { label: 'wrong_person', value: 4 },
  { label: 'auto_reply', value: 11 },
  { label: 'unclassified', value: 3 },
]

// ======================================================================
// Remarketing Analytics  (mock — shaped like a /api/analytics response;
// real impl would aggregate from Postgres, ClickHouse later)
// ======================================================================

export const analyticsKpis = [
  { key: 'revenue', label: 'Revenue', value: 684240, money: true, delta: 0.184 },
  { key: 'contracts', label: 'Contracts', value: 482, delta: 0.121 },
  { key: 'reply_rate', label: 'Reply Rate', value: 0.118, pct: true, delta: 0.024 },
  { key: 'contract_conversion', label: 'Contract Conversion', value: 0.057, pct: true, delta: 0.008 },
  { key: 'revenue_per_customer', label: 'Revenue / Remarketed Customer', value: 8.31, money: true, delta: 0.031 },
  { key: 'avg_deal', label: 'Average Deal', value: 1420, money: true, delta: -0.014 },
]

// vertical funnel — each block is clickable in the UI
export const analyticsFunnel = [
  { key: 'sleeping', label: 'Sleeping customers', count: 124820, drill: 0 },
  { key: 'enrolled', label: 'Enrolled', count: 82410, drill: 1 },
  { key: 'contacted', label: 'Contacted', count: 71280, drill: 2 },
  { key: 'replies', label: 'Replies', count: 8421, drill: 3 },
  { key: 'qualified', label: 'Qualified', count: 6932, drill: 4 },
  { key: 'manager', label: 'Manager contacts', count: 4218, drill: 5 },
  { key: 'quotes', label: 'Quotes', count: 1284, drill: 6 },
  { key: 'contracts', label: 'Contracts', count: 482, drill: 7 },
  { key: 'revenue', label: 'Revenue', count: 684240, money: true, drill: 7 },
]

export const revenueTrend = [
  { label: 'W1', value: 38200 }, { label: 'W2', value: 41100 }, { label: 'W3', value: 52400 },
  { label: 'W4', value: 48900 }, { label: 'W5', value: 61200 }, { label: 'W6', value: 57800 },
  { label: 'W7', value: 66500 }, { label: 'W8', value: 72100 }, { label: 'W9', value: 69400 },
  { label: 'W10', value: 81200 }, { label: 'W11', value: 77600 }, { label: 'W12', value: 89440 },
]

export const replyIntent = [
  { label: 'Interested', count: 3540, pct: 0.42 },
  { label: 'Callback', count: 1768, pct: 0.21 },
  { label: 'Not interested', count: 1179, pct: 0.14 },
  { label: 'Question', count: 924, pct: 0.11 },
  { label: 'Complaint', count: 312, pct: 0.04 },
  { label: 'Other', count: 698, pct: 0.08 },
]

export const campaignPerf = [
  { name: 'Win Back 90 Days', reply_rate: 0.084 },
  { name: 'Expired Quotes Q3', reply_rate: 0.069 },
  { name: 'Cold List Spring', reply_rate: 0.052 },
]

export const aiPerf = { accuracy: 0.942, confidence: 0.918, fallback: 0.021 }

export const revenueByCampaign = [
  { name: 'Win Back 90 Days', value: 240000 },
  { name: 'Expired Quotes Q3', value: 180000 },
  { name: 'Cold List Spring', value: 92000 },
  { name: 'Referral Nudge', value: 172240 },
]

// the "AI → Revenue" table the business actually cares about
export const aiIntentRevenue = [
  { intent: 'INTERESTED', replies: 3540, contracts: 312, conversion: 0.0881 },
  { intent: 'CALL_BACK', replies: 1768, contracts: 97, conversion: 0.0549 },
  { intent: 'QUESTION', replies: 924, contracts: 31, conversion: 0.0335 },
  { intent: 'NOT_INTERESTED', replies: 817, contracts: 2, conversion: 0.0024 },
  { intent: 'COMPLAINT', replies: 312, contracts: 0, conversion: 0 },
]

// --- drill-down: customers + per-customer journey -----------------------
const CUST_STAGES = ['enrolled', 'contacted', 'replied', 'qualified', 'manager', 'quote', 'contract']
const firstNames = ['Emma', 'Liam', 'Olivia', 'Noah', 'Ava', 'William', 'Sophia', 'James', 'Isabella', 'Lucas', 'Mia', 'Henry', 'Charlotte', 'Ethan']
const lastNames = ['Clark', 'Reyes', 'Bauer', 'Nowak', 'Costa', 'Haddad', 'Petit', 'Kaur', 'Berg', 'Ricci', 'Fournier', 'Marek', 'Dubois', 'Larsen']
const replyQuotes = [
  "Yes, I'm interested — please call me this week.",
  'Can you send pricing for 6 windows?',
  "Not right now, maybe in the spring.",
  "Wrong person, I never asked for a quote.",
  "Sounds good, what's the lead time?",
  "Please stop emailing me.",
]
const intents = ['INTERESTED', 'CALL_BACK', 'QUESTION', 'NOT_INTERESTED']
const sentiments = { INTERESTED: 'POSITIVE', CALL_BACK: 'POSITIVE', QUESTION: 'NEUTRAL', NOT_INTERESTED: 'NEGATIVE' }

function buildTimeline(progress, { intent, confidence, revenue }) {
  const t = []
  t.push({ date: 'Aug 02', kind: 'enter', text: 'Entered Remarketing' })
  t.push({ date: 'Aug 03', kind: 'email', text: 'Email #1 sent' })
  if (progress >= 2) t.push({ date: 'Aug 06', kind: 'email', text: 'Email #2 sent' })
  if (progress >= 3) {
    t.push({ date: 'Aug 08', kind: 'reply', text: 'Customer replied', meta: replyQuotes[(confidence * 97) | 0 % replyQuotes.length] })
    t.push({
      date: 'Aug 08',
      kind: 'ai',
      text: 'AI classified reply',
      meta: `INTENT: ${intent} · SENTIMENT: ${sentiments[intent]} · CONFIDENCE: ${confidence.toFixed(2)}`,
    })
    t.push({ date: 'Aug 08', kind: 'task', text: 'Manager task created' })
  }
  if (progress >= 4) t.push({ date: 'Aug 09', kind: 'qualified', text: 'Qualified by manager' })
  if (progress >= 5) t.push({ date: 'Aug 09', kind: 'manager', text: 'Manager contacted customer' })
  if (progress >= 6) t.push({ date: 'Aug 11', kind: 'quote', text: 'Quote sent' })
  if (progress >= 7) {
    t.push({ date: 'Aug 15', kind: 'contract', text: 'Contract signed' })
    t.push({ date: 'Aug 15', kind: 'revenue', text: `Revenue: $${revenue.toLocaleString('en-US')}` })
  }
  return t
}

export const analyticsCustomers = Array.from({ length: 14 }, (_, i) => {
  const progress = [7, 7, 6, 6, 5, 5, 4, 4, 3, 3, 3, 2, 2, 1][i]
  const intent = intents[i % intents.length]
  const confidence = 0.72 + ((i * 37) % 27) / 100
  const revenue = progress >= 7 ? [4820, 3980, 6100, 2450][i % 4] : 0
  return {
    id: 18200 + i * 7,
    name: `${firstNames[i]} ${lastNames[i]}`,
    campaign: campaignPerf[i % campaignPerf.length].name,
    flow: ['Win-back Q3', 'Long-term nurture', 'Hot handoff'][i % 3],
    progress,
    stage: CUST_STAGES[progress - 1],
    intent,
    confidence,
    revenue,
    timeline: buildTimeline(progress, { intent, confidence, revenue }),
  }
})

export const liveFeedSeed = [
  { kind: 'reply', text: 'Customer replied', meta: 'AI: INTERESTED · confidence 0.96' },
  { kind: 'contract', text: 'Contract signed', meta: '$4,820' },
  { kind: 'enter', text: 'Customer entered Flow #12', meta: null },
  { kind: 'manager', text: 'Manager contacted customer', meta: 'response 8m' },
  { kind: 'ai', text: 'AI classified reply', meta: 'CALLBACK · HIGH' },
  { kind: 'quote', text: 'Quote sent', meta: '$2,150' },
  { kind: 'reply', text: 'Customer replied', meta: 'AI: QUESTION · confidence 0.81' },
]

export function fmtNum(n) {
  if (n == null) return '—'
  if (n >= 1000) return n.toLocaleString('en-US')
  return String(n)
}
export function fmtMoney(n) {
  if (n == null) return '—'
  if (Math.abs(n) >= 1000) return '$' + Math.round(n / 1000) + 'K'
  return '$' + n.toLocaleString('en-US', { maximumFractionDigits: 2 })
}
export function fmtPct(n, digits = 1) {
  return (n * 100).toFixed(digits) + '%'
}

export function clientName(id) {
  return clients.find((c) => c.id === id)?.name ?? `client #${id}`
}
export function managerName(id) {
  return managers.find((m) => m.id === id)?.name ?? '—'
}
export function campaignName(id) {
  return campaigns.find((c) => c.id === id)?.name ?? `campaign #${id}`
}
export function fmt(ts) {
  if (!ts) return '—'
  return new Date(ts).toLocaleString('en-CA', { dateStyle: 'medium', timeStyle: 'short' })
}
/** map a domain value to a PrimeVue 3 Tag `severity` (success|info|warning|danger|secondary|contrast) */
export function sentimentTone(s) {
  return (
    {
      interested: 'success',
      question: 'info',
      not_now: 'warning',
      unsubscribe: 'danger',
      wrong_person: 'danger',
      auto_reply: 'contrast',
      request_measurement: 'success',
      request_quote: 'info',
      call_back: 'info',
    }[s] || 'warning'
  )
}

export function intentTone(s) {
  return sentimentTone(s)
}
export function statusTone(s) {
  return (
    {
      open: 'info',
      in_progress: 'warning',
      waiting_customer: 'contrast',
      resolved: 'success',
      dismissed: 'secondary',
      published: 'success',
      pending: 'warning',
      failed: 'danger',
      healthy: 'success',
      degraded: 'warning',
      down: 'danger',
      active: 'success',
      paused: 'warning',
      draft: 'warning',
      archived: 'secondary',
      new: 'info',
      offered: 'info',
      scheduled: 'success',
      confirmed: 'success',
      en_route: 'warning',
      completed: 'success',
      cancelled: 'secondary',
      no_show: 'danger',
      measurement: 'info',
      consultation: 'info',
      quote_review: 'info',
      ai_auto: 'contrast',
      manager: 'info',
      ok: 'success',
      partial: 'warning',
      queued: 'info',
      synced: 'success',
    }[s] || 'secondary'
  )
}
