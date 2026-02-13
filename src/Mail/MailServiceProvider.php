<?php

declare(strict_types=1);

namespace Plugs\Mail;

use Plugs\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Register the Mail service.
     */
    public function register(): void
    {
        $this->app->singleton('mail', function ($app) {
            return new MailService(config('mail', []));
        });
    }

    /**
     * Boot the service.
     */
    public function boot(): void
    {
        //
    }
}
