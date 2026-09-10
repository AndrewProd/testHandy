<?php

namespace App\Providers;

use App\Support\Nats\NatsManager;
use Illuminate\Support\ServiceProvider;

class NatsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NatsManager::class, function ($app) {
            return new NatsManager($app['config']->get('nats'));
        });
    }
}
