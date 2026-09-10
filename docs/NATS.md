# NATS bus, transactional outbox, and Surveyor

This document covers the real message bus that was added on top of the original
skeleton. `TASK.md` explicitly says a real NATS is *not* required for the
assignment — this is here to run the pipeline end to end and to have something
to point Surveyor at.

## Containers

| Service         | Port(s)        | What it is |
|-----------------|----------------|------------|
| `nats`          | 4222, 8222     | NATS 2.10 with JetStream. Config: `docker/nats/nats-server.conf`. |
| `relay`         | –              | `php artisan outbox:relay` — drains the Postgres outbox into JetStream. |
| `consumer`      | –              | `php artisan nats:consume-replies` — pulls `reply.received` and dispatches the job. |
| `web`           | 8000           | `php artisan serve` — the manual test-harness page (`routes/web.php`). |
| `nats-surveyor` | 7777           | Scrapes NATS over the system account, exposes `/metrics`. |
| `prometheus`    | 9090           | Scrapes Surveyor (`docker/prometheus/prometheus.yml`). |
| `grafana`       | 3000           | Anonymous admin. Dashboards auto-provisioned from `docker/grafana`. |

`nats-server.conf` defines two accounts: `APP` (the application connects here,
JetStream enabled) and `SYS` (the system account Surveyor needs to read server
stats).

## Streams

Created by `php artisan nats:setup` (also wired into `make up`):

| Stream     | Subjects           | Producer            | Consumer |
|------------|--------------------|---------------------|----------|
| `INBOUND`  | `reply.received`   | email-gateway (sim) | `nats:consume-replies` → `ProcessInboundReplyJob` |
| `OUTBOUND` | `events.>`         | `outbox:relay`      | `nats:consume-events` (demo/audit) |

Both streams have a 120s JetStream de-duplication window keyed on the
`Nats-Msg-Id` header.

## The flow

```
                    email-gateway (simulated by nats:publish-fixture)
                                    │  publish reply.received  (Nats-Msg-Id = event_id)
                                    ▼
                          ┌──────────────────┐
                          │ JetStream INBOUND│  ← dedupes on event_id
                          └──────────────────┘
                                    │  pull
                                    ▼
                       nats:consume-replies (durable "reply-center")
                                    │  ProcessInboundReplyJob::dispatch()
                                    ▼
        ┌───────────────────────  DB::transaction  ───────────────────────┐
        │  IdempotencyGuard.claim(tenant_id, event_id)                    │
        │  reply_tasks   insertOrIgnore  (unique tenant_id+event_id)      │
        │  campaign_enrollments  stop / clients.suppressed_at             │
        │  outbox_events insert  (dedupe_key = event_id)   ◄── same txn   │
        └────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
                        outbox:relay  (FOR UPDATE SKIP LOCKED)
                                    │  publish events.reply.processed (Nats-Msg-Id = dedupe_key)
                                    ▼
                          ┌───────────────────┐
                          │ JetStream OUTBOUND │
                          └───────────────────┘
                                    │
                                    ▼
                          nats:consume-events (logs it)
```

### Why the outbox

The state change (`reply_tasks` + campaign effects) and the "I published an
event" fact have to be atomic. Two-phase commit across Postgres and NATS is not
worth it, so instead the event is written to `outbox_events` *in the same
transaction* as the state change. If the transaction rolls back, there is no
event. If it commits, the row is there and the relay will publish it eventually.

The relay:

1. `SELECT ... WHERE status='pending' AND available_at <= now() ORDER BY id
   FOR UPDATE SKIP LOCKED LIMIT n` — several relays can run at once, each takes
   a disjoint slice.
2. Publishes each row to JetStream and waits for the PubAck.
3. Success → `status='published'`. Failure → `attempts++`,
   `available_at = now() + backoff` (exponential, capped), and after
   `max_attempts` the row goes to `status='failed'` for a human to look at.

At-least-once, not exactly-once: a relay can publish, then die before writing
`status='published'`. The row is picked up again and re-published — and
JetStream drops it because `Nats-Msg-Id` (= `dedupe_key` = `event_id`) is still
in the dedupe window. Downstream consumers must be idempotent regardless, which
is already true here (`IdempotencyGuard`).

## Test-harness page

<http://localhost:8000> (`web` service, `routes/web.php` +
`app/Support/HarnessPage.php`).

- `GET /` — a form, then a **job feed**: for the last ~15 messages on the
  INBOUND stream (read straight from JetStream) it shows the **exact payload the
  job was dispatched with** plus what it did — `задача #N · <sentiment> ·
  <status>`, the client, and the bus status (`published` / `pending` /
  `failed`). Unknown sender / duplicate shows "задача не создана".
- `POST /publish` — builds a `reply.received` event (`event_id = evt_web_<ts>_<rand>`,
  `Nats-Msg-Id = event_id`), publishes it to the INBOUND stream, redirects back.

The already-running `consumer` and `relay` services do the rest within ~1s, so
reloading the page shows the new card. This is the simple way to see "what data
did this job run with" — no CLI.

## Running it

```bash
make up                     # brings everything up incl. nats:setup
open http://localhost:8000  # the harness page
make bus-demo               # publish fixture → consume → relay → consume events, once each
make surveyor               # prints all the URLs

# individually
make publish-fixture        # replay tests/Fixtures/inbound_events.json onto reply.received
make consume                # nats:consume-replies (long-running; also runs as the `consumer` service)
make relay                  # outbox:relay        (long-running; also runs as the `relay` service)
make consume-events         # nats:consume-events
```

Artisan commands:

| Command | Purpose |
|---|---|
| `nats:setup` | create the INBOUND / OUTBOUND streams (idempotent) |
| `nats:publish-fixture {file?}` | publish a JSON array of events to `reply.received` |
| `nats:consume-replies {--once} {--max=} {--batch=}` | consume `reply.received`, dispatch the job |
| `outbox:relay {--once} {--batch=} {--sleep=}` | publish pending `outbox_events` rows |
| `nats:consume-events {--once} {--batch=}` | consume `events.>` and log |

## Seeing the messages that flew

Grafana and the NATS monitoring endpoint (`:8222`) show **metrics and counters
only** — never message bodies.

- **Grafana** → *JetStream State and Metrics*: per-stream message counts, in/out
  rates, consumer pending / ack floor, bytes. *NATS Overview*: msgs/sec,
  connections.
- **`http://localhost:8222/jsz?streams=true&consumers=true`**: stream state —
  message count, `first_seq` / `last_seq`, each consumer's `delivered` and
  `ack_floor`. Still no payloads. (`/varz`, `/connz`, `/subsz` for
  server / connections / subscriptions.)

**Payloads** live in the JetStream streams themselves (retention is `limits`, so
acked messages stay). Read them with the `nats` CLI — it is not in the app
image, run it from `nats-box`:

```bash
make nats-cli ARGS="stream ls"                                   # streams + counts
make nats-cli ARGS="stream info INBOUND"                         # seqs, consumers, config
make nats-cli ARGS="stream get INBOUND --last-for reply.received"  # last event on a subject: body + headers
make nats-cli ARGS="stream view INBOUND"                         # page through every message
make nats-cli ARGS="consumer info INBOUND reply-center"          # delivered / acked / pending / redelivered
make nats-cli ARGS="sub reply.received"                          # watch new inbound events live
make bus-tail                                                    # watch EVERY subject live

# raw, without make:
docker run --rm -it --network testhandy_default natsio/nats-box \
  nats --server nats://app:app@nats:4222 stream get OUTBOUND --last-for events.reply.processed
```

`php artisan nats:consume-events --once` also prints every `events.*` payload
from OUTBOUND (and logs them via the `null`-safe logger).

## Surveyor / Grafana

- Surveyor metrics: <http://localhost:7777/metrics>
- Prometheus: <http://localhost:9090> (target `nats-surveyor` should be `up`)
- Grafana: <http://localhost:3000> — anonymous access is on (admin role);
  login is `admin` / `admin` if prompted.
  - **NATS** folder — the four upstream `nats-io/nats-surveyor` dashboards
    (NATS Surveyor, NATS Overview, JetStream State and Metrics, Clients),
    vendored under `docker/grafana/dashboards/`.
  - **Reply Pipeline** folder — `Reply Pipeline — jobs & events`
    (`docker/grafana/dashboards-app/reply-pipeline.json`), see below.
- NATS monitoring endpoint: <http://localhost:8222>

### Reply Pipeline dashboard

A Horizon-style view of the pipeline, built entirely from metrics Surveyor
already exports (`nats-surveyor` runs with `--jsz=all`, so per-stream and
per-consumer JetStream stats land in Prometheus). No extra tables, no app
instrumentation.

| Panel | Query (Prometheus) | Reads as |
|---|---|---|
| Events received | `increase(nats_stream_last_seq{stream="INBOUND"}[$__range])` | `reply.received` published in the window |
| Events published to bus | `increase(nats_stream_last_seq{stream="OUTBOUND"}[$__range])` | `events.reply.processed` the relay emitted |
| Processed (acked) | `increase(nats_consumer_ack_floor_stream_seq{consumer_name="reply-center"}[$__range])` | jobs that ran to completion + ack |
| Ack ratio | acked / received | 1.0 = everything got through |
| Messages/sec, delivered vs acked/sec | `rate(...)` | throughput; delivered outrunning acked = piling up |
| Backlog / In-flight / Redelivering | `nats_consumer_num_pending` / `num_ack_pending` / `num_redelivered` | Horizon's pending / reserved / retrying |
| Consumer lag | `delivered_stream_seq - ack_floor_stream_seq` | messages accepted but not yet done |
| Streams / Consumers tables | instant vectors | raw snapshot per stream & consumer |

**Payloads are not here** — Prometheus stores counts, not bodies. For "what was
in that event" use `make nats-cli ARGS="stream get INBOUND --last-for reply.received"`,
`make bus-tail`, or the harness page.

## Config

`config/nats.php`, driven by env (`.env`):

```
NATS_HOST=nats
NATS_PORT=4222
NATS_USER=app
NATS_PASS=app
```

Relay tuning: `OUTBOX_RELAY_BATCH`, `OUTBOX_RELAY_SLEEP`,
`OUTBOX_RELAY_MAX_ATTEMPTS`, `OUTBOX_RELAY_BACKOFF_BASE`,
`OUTBOX_RELAY_BACKOFF_CAP`.

## Tests

The bus itself is not required for the suite — everything runs against Postgres,
in a dedicated `reply_center_test` database (`phpunit.xml`) so `RefreshDatabase`
never wipes dev data.

- `tests/Feature/OutboxTest.php` — the job writes exactly one pending
  `outbox_events` row per event, in the same transaction as the reply task.
- `tests/Feature/OutboxRelayTest.php` — the relay's claim / publish / retry /
  give-up bookkeeping, with NATS mocked.

The real broker path is exercised manually with `make bus-demo`.
