<?php

declare(strict_types=1);

namespace Plugs\Pdf;

use Plugs\Support\ServiceProvider;

class PdfServiceProvider extends ServiceProvider
{
    /**
     * Register the PDF service.
     */
    public function register(): void
    {
        $this->app->singleton('pdf', function ($app) {
            return new Pdf($app->make(\Plugs\View\ViewEngine::class));
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
