.PHONY: up install migrate seed test shell down nats-setup relay consume consume-events publish-fixture bus-demo surveyor web nats-cli bus-tail

up:
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app cp -n .env.example .env || true
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --seed
	docker compose exec app php artisan nats:setup

install:
	docker compose exec app composer install

migrate:
	docker compose exec app php artisan migrate:fresh --seed

# Tests run against reply_center_test (see phpunit.xml) so RefreshDatabase
# never touches dev data. The createdb is a no-op once it exists.
test:
	-docker compose exec -T db createdb -U app reply_center_test 2>/dev/null
	docker compose exec app vendor/bin/phpunit

shell:
	docker compose exec app bash

down:
	docker compose down -v

# --- NATS / outbox -----------------------------------------------------------

nats-setup:
	docker compose exec app php artisan nats:setup

relay:
	docker compose exec app php artisan outbox:relay

consume:
	docker compose exec app php artisan nats:consume-replies

consume-events:
	docker compose exec app php artisan nats:consume-events

publish-fixture:
	docker compose exec app php artisan nats:publish-fixture

# One-shot end-to-end demo. The `relay` and `consumer` services are already
# running and will pick the events up on their own; we publish the fixture,
# give them a moment, then show what landed in the DB and drain the audit
# consumer so you can see the outbound events too.
bus-demo:
	docker compose exec app php artisan nats:setup --fresh
	docker compose exec app php artisan migrate:fresh --seed
	docker compose exec app php artisan nats:publish-fixture
	sleep 3
	docker compose exec app php artisan nats:consume-events --once
	docker compose exec db psql -U app -d reply_center -c "SELECT id,client_id,sentiment,status FROM reply_tasks ORDER BY id;" -c "SELECT status,count(*) FROM outbox_events GROUP BY status;"

# Raw NATS CLI via nats-box: message payloads, live tail, stream/consumer state.
# Example: make nats-cli ARGS="stream get INBOUND --last-for reply.received"
NATS_URL = nats://app:app@nats:4222
nats-cli:
	docker run --rm -it --network testhandy_default natsio/nats-box \
	  nats --server $(NATS_URL) $(ARGS)

# Tail every message flowing on the bus (Ctrl+C to stop).
bus-tail:
	docker run --rm -it --network testhandy_default natsio/nats-box \
	  nats --server $(NATS_URL) sub ">"

surveyor:
	@echo "Test harness : http://localhost:8000"
	@echo "Surveyor     : http://localhost:7777/metrics"
	@echo "Prometheus   : http://localhost:9090"
	@echo "Grafana      : http://localhost:3000  (anonymous admin; login admin/admin)"
	@echo "NATS mon     : http://localhost:8222"

# The test harness page runs as the `web` service on http://localhost:8000
# (create an event, watch consumer + relay turn it into a task and an event).
web:
	@echo "Test harness: http://localhost:8000"
	docker compose logs -f web
