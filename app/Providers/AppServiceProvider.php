<?php

namespace App\Providers;

use App\Contracts\OutboundMailer;
use App\Contracts\SentimentClassifier;
use App\Services\FakeFlakyClassifier;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\SendGridMailer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SentimentClassifier::class, FakeFlakyClassifier::class);
        $this->app->bind(OutboundMailer::class, function ($app) {
            return match ($app['config']->get('outbound_mail.driver')) {
                'sendgrid' => $app->make(SendGridMailer::class),
                default => $app->make(MailgunMailer::class),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
