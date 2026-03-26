<layout name="layouts.app">
    <style>
        body {
            background-color: #f0f2f5 !important;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .test-checkout-wrapper {
            max-width: 900px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .test-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.04);
            overflow: hidden;
            border: 1px solid #eef2f6;
        }

        .test-header {
            padding: 40px;
            background: #fafbfc;
            border-bottom: 1px solid #f1f4f8;
            text-align: center;
        }

        .test-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1a202c;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .test-header p {
            color: #718096;
            margin-top: 10px;
            font-size: 16px;
        }

        .test-body {
            padding: 40px;
        }

        .platform-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .platform-option {
            position: relative;
            cursor: pointer;
        }

        .platform-option input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .platform-box {
            padding: 24px;
            border: 2px solid #edf2f7;
            border-radius: 18px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #fff;
        }

        .platform-option:hover .platform-box {
            border-color: #cbd5e0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .platform-option input:checked + .platform-box {
            border-color: #4c51bf;
            background: #f7fafc;
        }

        .platform-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .platform-name {
            font-weight: 700;
            font-size: 16px;
            color: #2d3748;
        }

        .platform-status {
            font-size: 12px;
            margin-top: 4px;
            font-weight: 600;
        }

        .status-enabled { color: #38a169; }
        .status-disabled { color: #e53e3e; }

        .check-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 20px;
            height: 20px;
            background: #4c51bf;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.2s;
        }

        .platform-option input:checked ~ .check-badge {
            opacity: 1;
            transform: scale(1);
        }

        .form-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #f1f4f8;
        }

        .test-input-group {
            margin-bottom: 20px;
        }

        .test-label {
            display: block;
            font-weight: 700;
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .test-input {
            width: 100%;
            padding: 14px 18px;
            border-radius: 12px;
            border: 2px solid #edf2f7;
            font-size: 16px;
            font-weight: 500;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .test-input:focus {
            border-color: #4c51bf;
        }

        .amount-input-wrapper {
            position: relative;
        }

        .currency-symbol {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 800;
            color: #a0aec0;
            font-size: 18px;
        }

        .amount-input {
            padding-left: 45px !important;
            font-size: 24px !important;
            font-weight: 800 !important;
            color: #2d3748;
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            border-radius: 14px;
            border: none;
            background: #1a202c;
            color: white;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: #2d3748;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .submit-btn:active {
            transform: scale(0.98);
        }

        .submit-btn:disabled {
            background: #a0aec0;
            cursor: not-allowed;
            transform: none;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        .footer-note {
            text-align: center;
            margin-top: 30px;
            color: #a0aec0;
            font-size: 14px;
        }
    </style>

    <div class="test-checkout-wrapper">
        <div class="test-card">
            <div class="test-header">
                <h1>{{ $title ?? 'Payment Selection' }}</h1>
                <p>Choose your preferred payment method to proceed.</p>
            </div>

            <form action="/payment/checkout" method="POST" id="testPaymentForm">
                @csrf
                <div class="test-body">
                    <div class="platform-grid">
                        @foreach($platforms as $slug => $platform)
                        @php
                            $isEnabled = ($settings["payment_{$slug}_enabled"] ?? 'false') === 'true';
                        @endphp
                        <label class="platform-option">
                            <input type="radio" name="platform" value="{{ $slug }}" {{ $slug === $defaultPlatform ? 'checked' : '' }}>
                            <div class="platform-box">
                                <div class="platform-icon">
                                    @if($slug === 'stripe')
                                        <i class="bi bi-stripe text-primary"></i>
                                    @elseif($slug === 'paystack')
                                        <i class="bi bi-credit-card-2-front text-info"></i>
                                    @else
                                        <i class="bi bi-wallet2"></i>
                                    @endif
                                </div>
                                <div class="platform-name">{{ $platform['name'] }}</div>
                                <div class="platform-status {{ $isEnabled ? 'status-enabled' : 'status-disabled' }}">
                                    {{ $isEnabled ? 'Active' : 'Not Configured' }}
                                </div>
                            </div>
                            <div class="check-badge"><i class="bi bi-check-lg"></i></div>
                        </label>
                        @endforeach
                    </div>

                    <div class="form-section">
                        <div class="test-input-group">
                            <label class="test-label">Amount</label>
                            <div class="amount-input-wrapper">
                                <span class="currency-symbol">{{ $defaultCurrency === 'NGN' ? '₦' : '$' }}</span>
                                <input type="number" name="amount" value="50.00" step="0.01" min="1" class="test-input amount-input" required>
                            </div>
                        </div>

                        <div class="test-input-group">
                            <label class="test-label">Email Address</label>
                            <input type="email" name="email" value="{{ \Plugs\Facades\Auth::check() ? \Plugs\Facades\Auth::user()->email : 'test@example.com' }}" class="test-input" placeholder="customer@example.com" required>
                        </div>

                        <div class="test-input-group">
                            <label class="test-label">Description</label>
                            <input type="text" name="description" value="Multi-Platform Test Payment" class="test-input" placeholder="e.g. Order #12345">
                        </div>

                        <input type="hidden" name="currency" value="{{ $defaultCurrency }}">

                        <button type="submit" class="submit-btn" id="submitBtn">
                            <div class="spinner" id="btnSpinner"></div>
                            <span id="btnText">Initialize Payment</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="footer-note">
            <i class="bi bi-shield-lock-fill"></i> Secure Test Environment • Multi-Platform Support
        </div>
    </div>

    <script>
        document.getElementById('testPaymentForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const spinner = document.getElementById('btnSpinner');
            const text = document.getElementById('btnText');

            btn.disabled = true;
            spinner.style.display = 'block';
            text.innerText = 'Processing...';
        });

        // Dynamic currency symbol logic
        const platformInputs = document.querySelectorAll('input[name="platform"]');
        const currencySymbol = document.querySelector('.currency-symbol');
        const currencyInput = document.querySelector('input[name="currency"]');

        function updateCurrency() {
            const selectedPlatformInput = document.querySelector('input[name="platform"]:checked');
            if (!selectedPlatformInput) return;
            
            const selectedPlatform = selectedPlatformInput.value;
            if (selectedPlatform === 'paystack' || selectedPlatform === 'flutterwave') {
                currencySymbol.innerText = '₦';
                currencyInput.value = 'NGN';
            } else {
                currencySymbol.innerText = '$';
                currencyInput.value = 'USD';
            }
        }

        platformInputs.forEach(input => {
            input.addEventListener('change', updateCurrency);
        });

        // Initialize on load
        updateCurrency();
    </script>
</layout>
