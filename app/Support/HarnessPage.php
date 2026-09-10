<?php

namespace App\Support;

use App\Models\OutboxEvent;
use Illuminate\Support\Collection;

/**
 * Renders the local NATS test-harness page (routes/web.php). Plain string
 * templating on purpose - the skeleton has no Blade views and this is a dev
 * tool, not product UI.
 */
class HarnessPage
{
    public static function render(
        Collection $clients,
        Collection $tasks,
        Collection $events,
        ?string $published,
        ?string $error,
    ): string {
        $token = csrf_token();

        $options = $clients
            ->map(fn ($c) => '<option value="' . e($c->email) . '">tenant ' . e($c->tenant_id) . ' &mdash; ' . e($c->name) . '</option>')
            ->implode('');

        $banner = '';
        if ($published) {
            $banner = '<div class="ok">Опубликовано <code>' . e($published) . '</code> в <code>reply.received</code>.
                Воркеры <code>consumer</code> и <code>relay</code> подхватят за ~1&nbsp;сек.
                <a href="/">↻ обновить</a></div>
                <meta http-equiv="refresh" content="2;url=/">';
        }
        if ($error) {
            $banner = '<div class="err">Ошибка публикации: ' . e($error) . '</div>';
        }

        $taskRows = $tasks->map(fn ($t) => '<tr>
                <td>' . e($t->id) . '</td>
                <td><code>' . e($t->event_id) . '</code></td>
                <td>' . e($t->tenant_id) . '</td>
                <td>' . e($t->email ?? '—') . '</td>
                <td>' . ($t->sentiment ? e($t->sentiment) : '<span class="muted">null</span>') . '</td>
                <td>' . e($t->status) . '</td>
                <td class="muted">' . e($t->created_at) . '</td>
            </tr>')->implode('');

        $eventRows = $events->map(fn (OutboxEvent $ev) => '<tr>
                <td>' . e($ev->id) . '</td>
                <td><code>' . e($ev->subject) . '</code></td>
                <td><span class="pill ' . e($ev->status) . '">' . e($ev->status) . '</span></td>
                <td>' . e($ev->attempts) . '</td>
                <td><code>' . e($ev->payload['sentiment'] ?? 'null') . '</code></td>
                <td class="muted">' . e(optional($ev->published_at)->toDateTimeString() ?? '—') . '</td>
                <td><pre>' . e(json_encode($ev->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</pre></td>
            </tr>')->implode('');

        return <<<HTML
        <!doctype html>
        <html lang="ru">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Reply Center — NATS test harness</title>
        <style>
          :root { color-scheme: light dark; }
          body { font: 14px/1.5 -apple-system, system-ui, sans-serif; margin: 0; padding: 2rem; max-width: 1000px; margin-inline: auto; }
          h1 { font-size: 1.2rem; }
          h2 { font-size: 1rem; margin-top: 2rem; }
          form { display: grid; gap: .75rem; padding: 1rem; border: 1px solid #8883; border-radius: 8px; }
          label { display: grid; gap: .25rem; font-weight: 600; }
          input, textarea, button { font: inherit; padding: .5rem; border: 1px solid #8886; border-radius: 6px; background: transparent; color: inherit; }
          textarea { min-height: 4rem; resize: vertical; }
          .row { display: flex; gap: 1rem; flex-wrap: wrap; }
          .row > label { flex: 1; min-width: 180px; }
          .check { flex-direction: row; align-items: center; font-weight: 400; }
          .check input { width: auto; }
          button { font-weight: 700; cursor: pointer; background: #2563eb; color: #fff; border: 0; }
          table { border-collapse: collapse; width: 100%; margin-top: .5rem; font-size: 13px; }
          th, td { text-align: left; padding: .35rem .5rem; border-bottom: 1px solid #8883; vertical-align: top; }
          code { font-family: ui-monospace, monospace; font-size: 12px; }
          pre { margin: 0; font-family: ui-monospace, monospace; font-size: 11px; white-space: pre-wrap; max-width: 360px; opacity: .85; }
          .muted { opacity: .6; }
          .ok { background: #16a34a22; border: 1px solid #16a34a88; padding: .75rem; border-radius: 8px; margin-bottom: 1rem; }
          .err { background: #dc262622; border: 1px solid #dc262688; padding: .75rem; border-radius: 8px; margin-bottom: 1rem; }
          .pill { padding: .1rem .4rem; border-radius: 4px; font-size: 12px; }
          .pill.published { background: #16a34a33; }
          .pill.pending { background: #f59e0b33; }
          .pill.failed { background: #dc262633; }
          .hint { opacity: .7; }
        </style>
        </head>
        <body>
        <h1>Reply Center — NATS test harness</h1>
        <p class="hint">Публикует событие <code>reply.received</code> в JetStream (стрим <code>INBOUND</code>).
        Запущенные сервисы <code>consumer</code> → <code>ProcessInboundReplyJob</code> и <code>relay</code> → <code>OUTBOUND</code> обрабатывают его сами.</p>

        $banner

        <form method="post" action="/publish">
          <input type="hidden" name="_token" value="$token">
          <div class="row">
            <label>tenant_id
              <input name="tenant_id" type="number" value="42" required>
            </label>
            <label>sender (email клиента)
              <input name="sender" list="clients" placeholder="d.walker@northshore-homes.ca" required>
              <datalist id="clients">$options</datalist>
            </label>
          </div>
          <label>body_plain
            <textarea name="body_plain" required>Sounds good. Can you call me Thursday afternoon?</textarea>
          </label>
          <label class="check"><input type="checkbox" name="auto_reply" value="1"> пометить как авто-ответ (Auto-Submitted)</label>
          <button type="submit">Опубликовать в NATS</button>
        </form>

        <h2>reply_tasks — последние 10</h2>
        <table>
          <thead><tr><th>id</th><th>event_id</th><th>tenant</th><th>client</th><th>sentiment</th><th>status</th><th>created</th></tr></thead>
          <tbody>$taskRows</tbody>
        </table>

        <h2>outbox_events — последние 10</h2>
        <table>
          <thead><tr><th>id</th><th>subject</th><th>status</th><th>attempts</th><th>sentiment</th><th>published</th><th>payload</th></tr></thead>
          <tbody>$eventRows</tbody>
        </table>
        </body>
        </html>
        HTML;
    }
}
