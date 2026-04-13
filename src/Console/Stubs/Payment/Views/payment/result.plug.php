<layout name="layouts.app">
    <style>
        body {
            background-color: #f8fafc !important;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
        }

        .result-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .result-nav {
            background: white;
            border-bottom: 1px solid #f1f5f9;
            padding: 16px 0;
        }
        .result-nav-inner {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .result-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #0f172a;
        }
        .result-brand-icon {
            width: 36px;
            height: 36px;
            background: #10b981;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 18px;
        }

        .result-main {
            flex: 1;
            max-width: 800px;
            margin: 0 auto;
            padding: 48px 24px 80px;
            width: 100%;
            box-sizing: border-box;
        }

        .result-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .result-header {
            padding: 28px 32px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .result-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .result-icon-success { background: #ecfdf5; color: #059669; }
        .result-icon-pending { background: #fffbeb; color: #d97706; }
        .result-icon-failed { background: #fef2f2; color: #dc2626; }

        .result-title { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; }
        .result-subtitle { font-size: 14px; color: #94a3b8; margin: 4px 0 0; }

        .result-body { padding: 32px; }

        .result-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 480px) {
            .result-grid { grid-template-columns: 1fr; }
        }

        .result-field {
            padding: 16px;
            border-radius: 14px;
            background: #f8fafc;
        }
        .result-field-label {
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 6px;
        }
        .result-field-value {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            word-break: break-all;
        }
        .result-field-value.mono {
            font-family: 'SF Mono', 'Cascadia Code', monospace;
            font-size: 13px;
        }

        .result-field-value.success { color: #059669; }
        .result-field-value.pending { color: #d97706; }
        .result-field-value.failed { color: #dc2626; }
        .result-field-value.amount { font-size: 20px; }

        .result-auth-url {
            padding: 16px;
            border-radius: 14px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            margin-top: 16px;
        }
        .result-auth-url p {
            margin: 0;
            font-family: 'SF Mono', 'Cascadia Code', monospace;
            font-size: 13px;
            color: #1d4ed8;
            word-break: break-all;
        }

        .result-raw {
            padding: 16px;
            border-radius: 14px;
            background: #f8fafc;
            margin-top: 16px;
        }
        .result-raw pre {
            margin: 8px 0 0;
            font-size: 12px;
            color: #475569;
            overflow-x: auto;
            max-height: 240px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .result-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .result-btn {
            padding: 14px 24px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .result-btn-primary {
            background: #0f172a;
            color: white;
        }
        .result-btn-primary:hover { background: #1e293b; transform: translateY(-1px); }
        .result-btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .result-btn-secondary:hover { background: #e2e8f0; }
    </style>

    <div class="result-container">
        <!-- Navbar -->
        <nav class="result-nav">
            <div class="result-nav-inner">
                <a href="/" class="result-brand">
                    <div class="result-brand-icon">P</div>
                    <span style="font-weight:800; font-size:18px;">{{ \Modules\Admin\Models\Setting::getValue('site_name', 'Plugs App') }}</span>
                </a>
                <a href="/payment/checkout" style="font-size:14px; font-weight:600; color:#64748b; text-decoration:none;">← Back to Checkout</a>
            </div>
        </nav>

        <div class="result-main">
            {!! \Plugs\Utils\FlashMessage::render() !!}

            <?php if (isset($response)): ?>
            <!-- Payment Initialization Result -->
            <?php $statusColor = $response->status === 'success' ? 'success' : ($response->status === 'failed' ? 'failed' : 'pending'); ?>
            <div class="result-card">
                <div class="result-header">
                    <div class="result-icon result-icon-<?= $statusColor ?>">
                        <?php if ($response->status === 'success'): ?>
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <?php elseif ($response->status === 'failed'): ?>
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        <?php else: ?>
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="result-title">Payment Initialized</h2>
                        <p class="result-subtitle">via <strong><?= ucfirst(htmlspecialchars($platform)) ?></strong> · <?= ucfirst(htmlspecialchars($paymentMode)) ?> mode</p>
                    </div>
                </div>

                <div class="result-body">
                    <div class="result-grid">
                        <div class="result-field">
                            <p class="result-field-label">Reference</p>
                            <p class="result-field-value mono"><?= htmlspecialchars($response->reference) ?></p>
                        </div>
                        <div class="result-field">
                            <p class="result-field-label">Status</p>
                            <p class="result-field-value <?= $statusColor ?>" style="text-transform:capitalize;"><?= htmlspecialchars($response->status) ?></p>
                        </div>
                        <div class="result-field">
                            <p class="result-field-label">Amount</p>
                            <p class="result-field-value amount"><?= htmlspecialchars($response->currency) ?> <?= number_format($response->amount, 2) ?></p>
                        </div>
                        <div class="result-field">
                            <p class="result-field-label">Message</p>
                            <p class="result-field-value"><?= htmlspecialchars($response->message ?? 'N/A') ?></p>
                        </div>
                    </div>

                    <?php if (!empty($response->authorization_url)): ?>
                    <div class="result-auth-url">
                        <p class="result-field-label" style="color:#3b82f6;">Authorization URL / Client Secret</p>
                        <p><?= htmlspecialchars($response->authorization_url) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($response->metadata)): ?>
                    <div class="result-raw">
                        <p class="result-field-label">Raw Gateway Response</p>
                        <pre><?= htmlspecialchars(json_encode($response->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($verification)): ?>
            <!-- Verification Result -->
            <?php $vColor = $verification->status === 'success' ? 'success' : ($verification->status === 'failed' ? 'failed' : 'pending'); ?>
            <div class="result-card">
                <div class="result-header">
                    <div class="result-icon result-icon-<?= $vColor ?>">
                        <?php if ($verification->status === 'success'): ?>
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <?php else: ?>
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="result-title">Payment Verified</h2>
                        <p class="result-subtitle">via <strong><?= ucfirst(htmlspecialchars($platform)) ?></strong></p>
                    </div>
                </div>

                <div class="result-body">
                    <div class="result-grid">
                        <div class="result-field">
                            <p class="result-field-label">Reference</p>
                            <p class="result-field-value mono"><?= htmlspecialchars($verification->reference) ?></p>
                        </div>
                        <div class="result-field">
                            <p class="result-field-label">Status</p>
                            <p class="result-field-value <?= $vColor ?>" style="text-transform:capitalize;"><?= htmlspecialchars($verification->status) ?></p>
                        </div>
                        <div class="result-field">
                            <p class="result-field-label">Amount</p>
                            <p class="result-field-value amount"><?= htmlspecialchars($verification->currency) ?> <?= number_format($verification->amount, 2) ?></p>
                        </div>
                        <div class="result-field">
                            <p class="result-field-label">Message</p>
                            <p class="result-field-value"><?= htmlspecialchars($verification->message ?? 'N/A') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="result-actions">
                <a href="/payment/checkout" class="result-btn result-btn-primary">← New Payment</a>
                <a href="/" class="result-btn result-btn-secondary">Back to Home</a>
            </div>
        </div>
    </div>
</layout>
