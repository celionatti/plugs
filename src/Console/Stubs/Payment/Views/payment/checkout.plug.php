<layout name="layouts.app">
    <style>
        body {
            background-color: #f8fafc !important;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
        }

        .checkout-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .checkout-nav {
            background: white;
            border-bottom: 1px solid #f1f5f9;
            padding: 16px 0;
        }
        .checkout-nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .checkout-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #0f172a;
        }
        .checkout-brand-icon {
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
        .checkout-brand-name {
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -0.5px;
        }
        .checkout-nav-link {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s;
        }
        .checkout-nav-link:hover { color: #10b981; }

        /* Main */
        .checkout-main {
            flex: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 48px 24px 80px;
            width: 100%;
            box-sizing: border-box;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 32px;
        }
        @media (min-width: 768px) {
            .checkout-grid { grid-template-columns: 3fr 2fr; }
        }

        /* Cards */
        .pay-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .pay-card-header {
            padding: 28px 32px;
            border-bottom: 1px solid #f1f5f9;
        }
        .pay-card-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }
        .pay-card-header p {
            margin: 4px 0 0;
            font-size: 14px;
            color: #94a3b8;
        }
        .pay-card-body {
            padding: 32px;
        }

        /* Form */
        .form-group {
            margin-bottom: 24px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
        }
        .form-label .required { color: #ef4444; }
        .form-input, .form-select {
            width: 100%;
            padding: 14px 18px;
            border-radius: 14px;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            font-size: 15px;
            font-weight: 500;
            color: #0f172a;
            font-family: inherit;
            transition: all 0.2s;
            box-sizing: border-box;
            outline: none;
        }
        .form-input:focus, .form-select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.08);
            background: white;
        }
        .form-input-lg {
            font-size: 28px;
            font-weight: 800;
            padding: 18px 20px 18px 48px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 480px) {
            .form-row { grid-template-columns: 1fr; }
        }

        /* Amount wrapper */
        .amount-wrap {
            position: relative;
        }
        .amount-symbol {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 22px;
            font-weight: 800;
            color: #94a3b8;
        }

        /* Button */
        .pay-btn {
            width: 100%;
            padding: 18px;
            border-radius: 16px;
            border: none;
            background: #0f172a;
            color: white;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .pay-btn:hover {
            background: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);
        }
        .pay-btn:active { transform: scale(0.98); }
        .pay-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Sidebar */
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-label { color: #64748b; font-weight: 500; }
        .summary-value { color: #0f172a; font-weight: 700; }

        .platform-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 8px;
        }
        .platform-chip-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
        }
        .platform-chip-name {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            flex: 1;
        }

        .secure-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 12px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            font-size: 13px;
            font-weight: 600;
            color: #059669;
        }

        /* Multi chain */
        .chain-flow {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            padding: 12px 14px;
            border-radius: 12px;
            background: #faf5ff;
            border: 1px solid #e9d5ff;
            font-size: 13px;
            font-weight: 600;
            color: #7c3aed;
        }
        .chain-arrow { color: #c4b5fd; }

        .no-platforms-msg {
            text-align: center;
            padding: 32px 16px;
            color: #94a3b8;
            font-size: 15px;
        }
        .no-platforms-msg a {
            color: #10b981;
            font-weight: 700;
            text-decoration: none;
        }
        .no-platforms-msg a:hover { text-decoration: underline; }

        /* Spin animation */
        @keyframes spin { to { transform: rotate(360deg); } }
        .animate-spin { animation: spin 1s linear infinite; }
    </style>

    <div class="checkout-container">
        <!-- Navbar -->
        <nav class="checkout-nav">
            <div class="checkout-nav-inner">
                <a href="/" class="checkout-brand">
                    <div class="checkout-brand-icon">P</div>
                    <span class="checkout-brand-name">{{ \App\Models\Setting::getValue('site_name', 'Plugs App') }}</span>
                </a>
                <div style="display:flex; gap:20px; align-items:center;">
                    <a href="/" class="checkout-nav-link">Home</a>
                    @if(\Plugs\Facades\Auth::check())
                    <a href="/admin" class="checkout-nav-link">Dashboard</a>
                    @endif
                </div>
            </div>
        </nav>

        <!-- Main -->
        <div class="checkout-main">
            <div style="margin-bottom:32px;">
                <h1 style="font-size:28px; font-weight:800; color:#0f172a; margin:0;">Checkout</h1>
                <p style="font-size:15px; color:#94a3b8; margin:6px 0 0;">Complete your payment securely.</p>
            </div>

            {!! \Plugs\Utils\FlashMessage::render() !!}

            <div class="checkout-grid">
                <!-- Form -->
                <div>
                    <form action="/payment/checkout" method="POST" id="checkoutForm">
                        @csrf
                        <div class="pay-card">
                            <div class="pay-card-header">
                                <h2>Payment Details</h2>
                                <p>Enter the information below to proceed.</p>
                            </div>
                            <div class="pay-card-body">
                                <!-- Amount -->
                                <div class="form-group">
                                    <label class="form-label">Amount <span class="required">*</span></label>
                                    <div class="amount-wrap">
                                        <span class="amount-symbol"><?= ($defaultCurrency ?? 'USD') === 'NGN' ? '₦' : '$' ?></span>
                                        <input type="number" name="amount" step="0.01" min="1" value="1000" required class="form-input form-input-lg" placeholder="0.00" id="amountInput">
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="form-group">
                                    <label class="form-label">Email Address <span class="required">*</span></label>
                                    <input type="email" name="email" value="<?= htmlspecialchars(\Plugs\Facades\Auth::check() ? (\Plugs\Facades\Auth::user()->email ?? '') : '') ?>" required class="form-input" placeholder="your@email.com">
                                </div>

                                <!-- Description -->
                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description" value="Payment" class="form-input" placeholder="What is this payment for?">
                                </div>

                                <!-- Currency & Platform -->
                                <div class="form-row">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label">Currency</label>
                                        <select name="currency" class="form-select" id="currencySelect">
                                            <?php
                                            $currencies = ['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP', 'NGN' => 'NGN', 'GHS' => 'GHS', 'KES' => 'KES', 'ZAR' => 'ZAR', 'CAD' => 'CAD', 'AUD' => 'AUD'];
                                            foreach ($currencies as $code => $label):
                                            ?>
                                            <option value="<?= $code ?>" <?= ($defaultCurrency ?? 'USD') === $code ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label">Platform</label>
                                        <?php if (($paymentMode ?? 'single') === 'single'): ?>
                                        <select name="platform" class="form-select">
                                            <?php foreach ($enabledPlatforms as $slug => $platform): ?>
                                            <option value="<?= $slug ?>" <?= ($defaultPlatform ?? 'stripe') === $slug ? 'selected' : '' ?>><?= $platform['name'] ?></option>
                                            <?php endforeach; ?>
                                            <?php if (empty($enabledPlatforms)): ?>
                                            <option disabled selected>No platforms enabled</option>
                                            <?php endif; ?>
                                        </select>
                                        <?php else: ?>
                                        <div class="chain-flow">
                                            <?php
                                            $multiList = array_filter(explode(',', $settings['payment_multi_platforms'] ?? ''));
                                            $i = 0;
                                            foreach ($multiList as $s):
                                                if (!isset($enabledPlatforms[$s])) continue;
                                                if ($i > 0) echo '<span class="chain-arrow">→</span>';
                                                echo htmlspecialchars($enabledPlatforms[$s]['name']);
                                                $i++;
                                            endforeach;
                                            if ($i === 0) echo 'Not configured';
                                            ?>
                                        </div>
                                        <input type="hidden" name="platform" value="<?= htmlspecialchars($defaultPlatform ?? 'stripe') ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit -->
                            <div style="padding:24px 32px; background:#f8fafc; border-top:1px solid #f1f5f9;">
                                <?php if (!empty($enabledPlatforms)): ?>
                                <button type="submit" class="pay-btn" id="payBtn">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                    Pay Now
                                </button>
                                <?php else: ?>
                                <div class="no-platforms-msg">
                                    <p>No payment platforms are enabled.</p>
                                    <a href="/admin/payment">Go to Payment Settings →</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Sidebar -->
                <div>
                    <!-- Order Summary -->
                    <div class="pay-card" style="margin-bottom:20px;">
                        <div class="pay-card-header">
                            <h2>Summary</h2>
                        </div>
                        <div class="pay-card-body" style="padding-top:16px; padding-bottom:16px;">
                            <div class="summary-row">
                                <span class="summary-label">Mode</span>
                                <span class="summary-value" style="color: <?= ($paymentMode ?? 'single') === 'multi' ? '#7c3aed' : '#10b981' ?>;"><?= ucfirst($paymentMode ?? 'single') ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Default Gateway</span>
                                <span class="summary-value"><?= ucfirst($defaultPlatform ?? 'stripe') ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Currency</span>
                                <span class="summary-value"><?= $defaultCurrency ?? 'USD' ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Enabled</span>
                                <span class="summary-value"><?= count($enabledPlatforms) ?> platform<?= count($enabledPlatforms) !== 1 ? 's' : '' ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Enabled Platforms -->
                    <?php if (!empty($enabledPlatforms)): ?>
                    <div class="pay-card" style="margin-bottom:20px;">
                        <div class="pay-card-header">
                            <h2>Active Gateways</h2>
                        </div>
                        <div class="pay-card-body" style="padding-top:16px;">
                            <?php foreach ($enabledPlatforms as $slug => $platform): ?>
                            <div class="platform-chip">
                                <div class="platform-chip-dot"></div>
                                <span class="platform-chip-name"><?= htmlspecialchars($platform['name']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Secure Badge -->
                    <div class="secure-badge">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        Secured with 256-bit encryption
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('checkoutForm')?.addEventListener('submit', function() {
        var btn = document.getElementById('payBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" opacity="0.75"></path></svg> Processing...';
        }
    });
    </script>
</layout>
