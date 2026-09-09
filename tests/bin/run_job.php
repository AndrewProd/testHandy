<?php

use App\Jobs\ProcessInboundReplyJob;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$gate    = $argv[1] ?? null;
$payload = json_decode((string) stream_get_contents(STDIN), true);

if (!is_array($payload)) {
    fwrite(STDERR, "invalid payload\n");
    exit(2);
}

if (is_string($gate) && $gate !== '') {
    $deadline = microtime(true) + 10;

    while (!is_file($gate) && microtime(true) < $deadline) {
        usleep(500);
    }
}

$app->make(Illuminate\Contracts\Bus\Dispatcher::class)
    ->dispatchSync(new ProcessInboundReplyJob($payload));
