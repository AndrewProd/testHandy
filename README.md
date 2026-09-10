# Reply Center — тестовый стенд

Задание: см. [TASK.md](TASK.md). Как всё устроено — [ARCHITECTURE.md](ARCHITECTURE.md).

## Запуск

Нужен Docker с плагином compose.

```bash
make up
```

Команда поднимет Postgres 16 и PHP 8.3, поставит зависимости, накатит миграции и засеет демо-данные.

## Команды

```bash
make test      # прогнать тесты (отдельная база reply_center_test, dev-данные не трогаются)
make migrate   # пересоздать dev-БД и засеять заново
make shell     # bash внутри контейнера приложения
make down      # остановить и удалить тома
```

Postgres доступен снаружи на `localhost:55432` (`app` / `secret`, база `reply_center`) — если удобнее смотреть данные своим клиентом. Тесты идут в `reply_center_test`, так что `make test` больше не стирает демо-данные.

### Шина NATS (сверх задания)

`make up` поднимает также NATS с JetStream, воркеры `relay` / `consumer`, страницу
для ручных тестов и стек NATS Surveyor → Prometheus → Grafana. Подробно —
[docs/NATS.md](docs/NATS.md).

**Тестовая страница: http://localhost:8000** — форма публикует событие
`reply.received` в NATS; ниже лента джоб: по каждой видно **сырой payload,
с которым джоба летела**, и что она сделала (задача, sentiment, статус, шина).

```bash
make bus-demo         # publish fixture → consume → relay → consume events (по одному разу)
make surveyor         # печатает все ссылки (harness :8000, Grafana :3000, ...)
```

На тесты это не влияет — они по-прежнему целиком на Postgres.

### Дашборд-фронт (`front/`)

Vue 3 + Vite + **PrimeVue 3** (светлая тема `aura-light-blue`), без авторизации
и лицензии, на моковых данных — прототип консоли Remarketing / Reply Center.
Весь UI на компонентах PrimeVue.
Разделы: Reply Center, Drip Campaigns, Flow Handoff, Conversion Analytics,
AI (Classification Metrics + Prompt Management), Event History,
Suppression & Blacklist, Integrations, Field Teams, NATS Test Harness, Settings.

```bash
cd front && npm install && npm run dev   # http://localhost:5173
```

Подробно — [front/README.md](front/README.md).

## Что где лежит

```
app/
  Contracts/SentimentClassifier.php     интерфейс классификатора
  Services/FakeFlakyClassifier.php      фейк вместо LLM
  Services/MailGateway.php              заглушка отправки почты
  Support/IdempotencyGuard.php          дедупликация событий шины
  Jobs/ProcessInboundReplyJob.php       ← здесь работа
  Jobs/SendCampaignStepJob.php          отправка шага кампании
  Models/                               Client, CampaignEnrollment, ReplyTask, ProcessedEvent
database/
  migrations/                           схема
  seeders/DemoSeeder.php                демо-данные под фикстуру
tests/
  Fixtures/inbound_events.json          12 событий, снятых с прода
  Feature/SmokeTest.php                 проверка, что стенд живой
```

## Если что-то не поднялось

Напишите нам — чинить окружение это не часть задания, и время на это мы не засчитываем.
