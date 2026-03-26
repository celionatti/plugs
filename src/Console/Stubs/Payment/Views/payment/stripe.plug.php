<layout name="layouts.app">
    <style>
        body {
            background-color: #f8fafc !important;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
        }

        .stripe-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .stripe-nav {
            background: white;
            border-bottom: 1px solid #f1f5f9;
            padding: 16px 0;
        }
        .stripe-nav-inner {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stripe-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #0f172a;
        }
        .stripe-brand-icon {
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

        /* Main Form Area */
        .stripe-main {
            flex: 1;
            max-width: 600px;
            margin: 48px auto;
            padding: 0 24px;
            width: 100%;
            box-sizing: border-box;
        }

        .pay-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .pay-card-header {
            padding: 28px 32px;
            border-bottom: 1px solid #f1f5f9;
            text-align: center;
            background: #f8fafc;
        }
        .pay-card-header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
        }
        .pay-card-header p {
            margin: 6px 0 0;
            font-size: 14px;
            color: #64748b;
        }
        .pay-card-body {
            padding: 32px;
        }

        .amount-display {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .amount-display .label {
            display: block;
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .amount-display .value {
            font-size: 40px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        /* Button */
        .pay-btn {
            width: 100%;
            padding: 16px;
            margin-top: 32px;
            border-radius: 14px;
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
        .pay-btn:hover { background: #1e293b; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15); }
        .pay-btn:active { transform: scale(0.98); }
        .pay-btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; background: #475569; box-shadow: none; }

        .error-message {
            color: #ef4444; 
            margin-top: 16px; 
            font-size: 14px; 
            font-weight: 500;
            text-align: center;
            padding: 12px;
            background: #fef2f2;
            border-radius: 10px;
            display: none;
        }

        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px;
            font-size: 13px;
            font-weight: 600;
            color: #059669;
            margin-top: 16px;
            opacity: 0.8;
        }

        /* Spin animation */
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>

    <div class="stripe-container">
        <nav class="stripe-nav">
            <div class="stripe-nav-inner">
                <a href="/" class="stripe-brand">
                    <div class="stripe-brand-icon">P</div>
                    <span style="font-weight:800; font-size:18px;">Payment Gateway</span>
                </a>
                <span style="font-size:13px; font-weight:600; color:#cbd5e1; background:#f8fafc; padding:6px 12px; border-radius:20px; border:1px solid #e2e8f0;">Secure Checkout</span>
            </div>
        </nav>

        <div class="stripe-main">
            <div class="pay-card">
                <div class="pay-card-header">
                    <h2>Complete Payment</h2>
                    <p>Enter your card details to finalize the transaction</p>
                </div>
                
                <div class="pay-card-body">
                    <div class="amount-display">
                        <span class="label">Total Amount</span>
                        <span class="value"><?= htmlspecialchars($currency) ?> <?= number_format($amount, 2) ?></span>
                    </div>

                    <form id="payment-form">
                        <!-- Stripe Elements will inject the UI here -->
                        <div id="payment-element"></div>
                        
                        <!-- For surfacing Stripe errors -->
                        <div id="error-message" class="error-message"></div>
                        
                        <button id="submit" class="pay-btn">
                            <span id="button-text">Pay <?= htmlspecialchars($currency) ?> <?= number_format($amount, 2) ?></span>
                        </button>
                    </form>

                    <div class="secure-badge">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        Secured by Stripe
                    </div>
                </div>
            </div>
            
            <div style="text-align:center; margin-top:24px;">
                <a href="/payment/checkout" style="font-size:14px; font-weight:600; color:#64748b; text-decoration:none;">Cancel Payment</a>
            </div>
        </div>
    </div>

    <!-- Stripe.js SDK -->
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stripeKey = '<?= htmlspecialchars($publicKey) ?>';
            if (!stripeKey) {
                const errorEl = document.getElementById('error-message');
                errorEl.innerText = "Stripe public key is missing.";
                errorEl.style.display = 'block';
                document.getElementById('submit').disabled = true;
                return;
            }

            const stripe = Stripe(stripeKey);
            
            const options = {
                clientSecret: '<?= htmlspecialchars($clientSecret) ?>',
                appearance: {
                    theme: 'stripe',
                    variables: {
                        colorPrimary: '#0f172a',
                        colorBackground: '#ffffff',
                        colorText: '#0f172a',
                        colorDanger: '#ef4444',
                        fontFamily: 'Inter, system-ui, sans-serif',
                        spacingUnit: '4px',
                        borderRadius: '12px',
                    }
                }
            };

            const elements = stripe.elements(options);
            const paymentElement = elements.create('payment');
            paymentElement.mount('#payment-element');

            const form = document.getElementById('payment-form');
            const submitBtn = document.getElementById('submit');
            const btnText = document.getElementById('button-text');
            const errorContainer = document.getElementById('error-message');

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                
                submitBtn.disabled = true;
                errorContainer.style.display = 'none';
                btnText.innerText = 'Processing...';

                const confirmResult = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: '<?= url("/payment/verify") ?>?reference=<?= urlencode($reference) ?>&platform=stripe',
                        receipt_email: '<?= htmlspecialchars($email) ?>'
                    },
                });

                if (confirmResult.error) {
                    errorContainer.innerText = confirmResult.error.message;
                    errorContainer.style.display = 'block';
                    submitBtn.disabled = false;
                    btnText.innerText = 'Pay <?= htmlspecialchars($currency) ?> <?= number_format($amount, 2) ?>';
                }
            });
        });
    </script>
</layout>
