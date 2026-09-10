# Remarketing Console (front)

Vue 3 + Vite + **PrimeVue 3** (styled mode, `aura-light-blue` light theme)
dashboard prototype for the Reply Center / Remarketing platform. No auth, no
tokens, no license, no backend — every view reads from `src/data/mock.js`.
Purpose: iterate on the dashboard UI.

Setup follows <https://v3.primevue.org/setup/>: `app.use(PrimeVue)` in
`src/main.js`, theme + core CSS imported there
(`primevue/resources/themes/aura-light-blue/theme.css` +
`primevue/resources/primevue.min.css` + `primeicons/primeicons.css`).

All UI is built from PrimeVue components (`DataTable`, `Card`, `Tag`, `Dropdown`,
`Chart`, `Timeline`, `MeterGroup`, `Menu`, `Toast`, `Message`, …), registered
globally in `src/main.js`.

```bash
cd front
npm install
npm run dev      # http://localhost:5173
npm run build
```

## Sections

| Group | Section | What it shows |
|---|---|---|
| Remarketing | **Overview** | KPIs, replies/day, sentiment mix, campaign table |
| Remarketing | **Reply Center** | manager queue of reply tasks; filter by status / sentiment |
| Remarketing | **Reply Task** | one task: **manager status/sentiment/assignee controls**, the reply, AI classification, change log + bus event timeline |
| Remarketing | **Drip Campaigns** | sequences, enrollment split, pause/resume |
| Remarketing | **Flow Handoff** | move clients between remarketing flows + history |
| Remarketing | **Conversion Analytics** | funnel enrolled → … → signed, per campaign |
| AI | **Classification Metrics** | accuracy, failure breakdown, latency, cost |
| AI | **Prompt Management** | versioned classifier prompts — activate / roll back / edit / test against a sample; body + reasoning guidance |
| AI | **AI Requests** | immutable LLM-call audit (real API `/api/ai-requests`, Postgres `ai_requests`): per-call prompt, prompt version, tokens, cost, latency, structured output; summary + prompt-version stats |
| Data & Sync | **Event History** | full bus audit stream, filter by type, expand payload JSON |
| Data & Sync | **Suppression & Blacklist** | suppression list, source, CRM sync state |
| Data & Sync | **Integrations** | email-gateway, NATS, CRM connector, OpenAI, e-sign, payments |
| Operations | **Field Teams** | on-site visit teams + appointments booked from replies |
| Operations | **NATS Test Harness** | the `Reply Center — NATS test harness` page — publish a `reply.received` event, see payload + simulated job result |
| Operations | **Settings** | tenants, managers, reply-handling defaults |

## Wiring to the real backend

`vite.config.js` proxies `/api/harness/*` → `http://localhost:8000` (the Laravel
harness). The NATS Test Harness view currently simulates locally; swap
`simulate()` for a `fetch('/api/harness/publish', …)` to drive the real pipeline.
