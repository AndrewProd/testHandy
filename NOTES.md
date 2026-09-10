1. Если сломалось не ответил или вернул неправильный json, задача всё равно создаётся, просто sentiment = null.
Рассылка при этом останавливается, чтобы человеку не продолжали писать автоматически и переводчиться на решение в таску менеджеру.
Клиент не блокируется — блокировка происходит только при явной отписке.

2. Защита от дублей в воркере тоже что было на собесе.
Добавили уникальность по tenant_id + event_id и нормальный claim() через INSERT ... ON CONFLICT DO NOTHING.

3. Из репозитория взял как есть: FakeFlakyClassifier, модели, сидер, фикстуру событий, SendCampaignStepJob, MailGateway. 

Переписал то, без чего джоба была бы неправильной не запускалася без команды unique на событиях не было, tenant scope не был включён. На clients.email стоял глобальный unique — два тенанта с одним email не могли существовать.

Добавил в проект гитигнор и по мелочам из за чего не поднимался проект - не было artisan, .env.example, нормального phpunit.xml, CreatesApplication в TestCase. Не было config/logging.php, при этом в коде уже есть Log:: — без конфига падало. Это чинил как дырки окружения, не как архитектуру.

4. Тесты не писал — их набросал агент, я только прогнал и посмотрел, что результат на фикстуре меня устраивает.

Руками: джоба, claim(), unique в миграциях, явный tenant_id в запросах, unique на (tenant_id, email). Решение по сбою классификатора тоже моё: задача с sentiment = null, кампанию стопаем, suppress только на явной отписке. 

За агентом переписывал, если он начинал городить лишнее: TenantContext, лишние классы, комментарии. FakeFlakyClassifier и сами события в фикстуре не трогал.

5. Не трогал обработку ошибок классификатора и тестовую инфраструктуру евентов которые насобирались. Сбои закрыл в джобе.

---

## NATS + Outbox + Surveyor (сверх задания)

TASK.md прямо говорит, что настоящий NATS не нужен. Добавлено отдельно, чтобы
гонять пайплайн end-to-end и было что показать в Surveyor. Подробно —
`docs/NATS.md`.

- **Docker.** Сервисы `nats` (JetStream), `relay`, `consumer`, `nats-surveyor`,
  `prometheus`, `grafana`. Конфиг NATS — `docker/nats/nats-server.conf`
  (аккаунты `APP` и `SYS`; system account нужен Surveyor'у).
- **Outbox.** Таблица `outbox_events`. `App\Support\Outbox::emit()` пишет строку
  события **в той же транзакции**, что и `reply_tasks` / эффекты кампании (в
  `ProcessInboundReplyJob`). `dedupe_key = event_id` → ровно одно исходящее
  событие на входящее.
- **Relay.** `php artisan outbox:relay` — `SELECT ... FOR UPDATE SKIP LOCKED`,
  публикует в JetStream, ждёт PubAck. Ошибка → backoff (экспоненциальный, с
  потолком) и `attempts++`, после `max_attempts` строка → `failed`.
  Гарантия at-least-once: повторную публикацию гасит JetStream по `Nats-Msg-Id`.
- **Consumer.** `php artisan nats:consume-replies` — pull-consumer на
  `reply.received`, декодирует и вызывает `ProcessInboundReplyJob`. Кривой JSON
  → `term()`, ошибка джобы → `nack()` (redelivery безопасен — есть
  `IdempotencyGuard`).
- **Surveyor.** Апстримные дашборды `nats-io/nats-surveyor` в
  `docker/grafana/dashboards/`. Плюс свой **Reply Pipeline — jobs & events**
  (`docker/grafana/dashboards-app/reply-pipeline.json`): throughput, backlog,
  in-flight, retries, consumer lag — в стиле Horizon. Целиком на метриках,
  которые Surveyor уже отдаёт (`--jsz=all` → per-stream / per-consumer
  JetStream-статы в Prometheus). Без отдельных таблиц в БД и без инструментации
  приложения. Payload'ы там не показать — это `make nats-cli` / `make bus-tail`.
- **Тесты.** `tests/Feature/OutboxTest.php` (джоба пишет одну pending-строку в
  той же транзакции) и `tests/Feature/OutboxRelayTest.php` (claim / publish /
  retry / failed, NATS замокан). Живой брокер — вручную через `make bus-demo`.
- **Веб-страница для ручных тестов.** `routes/web.php` +
  `app/Support/HarnessPage.php`, сервис `web` на <http://localhost:8000>. Форма
  публикует `reply.received` в NATS; ниже — лента джоб: страница читает
  последние сообщения прямо из стрима INBOUND и по каждому показывает **payload,
  с которым летела джоба**, и результат (задача / sentiment / статус / шина).
  Пришлось донести отсутствующий HTTP-слой: `public/index.php`, `routes/web.php`,
  `config/session.php` + `config/view.php` (в скелете их не было), `web:` в
  `bootstrap/app.php`.

Правки не по теме NATS, но по ходу:

- В рабочей копии был закомментирован `use RefreshDatabase` в
  `tests/TestCase.php` — тесты текли друг в друга. Вернул.
- Тесты гоняли ту же базу `reply_center`, что и dev, поэтому `RefreshDatabase`
  вытирал демо-данные на каждом прогоне. Развёл: тесты теперь на отдельной базе
  `reply_center_test` (`phpunit.xml`), dev-база не трогается вообще. База
  создаётся `docker/db/init.sql` на свежем томе; для существующего — `make test`
  делает `createdb` идемпотентно. Из `docker-compose.yml` убран
  `DB_DATABASE` у сервиса `app` — как переменная окружения контейнера он
  перекрывал `phpunit.xml`, и тесты всё равно шли в dev-базу.

Клиент `basis-company/nats` (composer). В Dockerfile добавлены расширения
`pcntl` и `sockets`.