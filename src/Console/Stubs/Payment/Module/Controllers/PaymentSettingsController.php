<?php

declare(strict_types=1);

namespace Modules\Payment\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Modules\Payment\Services\PaymentSettingsService;

class PaymentSettingsController
{
    protected PaymentSettingsService $settingsService;

    public function __construct(PaymentSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Display payment settings page.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $settings = $this->settingsService->getPaymentSettings();
        $platforms = $this->settingsService->getAvailablePlatforms();

        return response(view('payment::settings.index', [
            'title' => 'Payment Settings',
            'settings' => $settings,
            'platforms' => $platforms,
        ]));
    }

    /**
     * Update payment settings.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $this->settingsService->updatePaymentSettings($request->getParsedBody());

        return ResponseFactory::redirect('/admin/payment')
            ->with('success', 'Payment configuration updated successfully.');
    }
}
