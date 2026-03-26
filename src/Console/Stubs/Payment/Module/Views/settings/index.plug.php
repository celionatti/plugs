@extends('admin::layouts.admin')

@section('title', 'Payment Settings')

@section('content')
<div class="mb-10 flex items-start justify-between">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Payment Configuration</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Configure payment gateways, manage API keys, and choose single or multi-gateway mode.</p>
    </div>
    <a href="/payment/checkout" target="_blank" class="px-6 py-3 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/40 hover:-translate-y-0.5 transition-all active:scale-95 flex items-center gap-2 whitespace-nowrap">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        Test Checkout <svg class="w-4 h-4 ml-1 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
    </a>
</div>

<div class="max-w-6xl">
    <form action="/admin/payment" method="POST" id="paymentSettingsForm">
        @csrf

        <!-- Payment Mode Selection -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden mb-8">
            <div class="p-8 border-b border-slate-50 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30">
                <h2 class="text-xl font-bold text-slate-800 dark:text-white">Gateway Mode</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Choose how your application processes payments.</p>
            </div>
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Single Gateway -->
                    <label class="relative cursor-pointer group" id="mode-single-card">
                        <input type="radio" name="payment_mode" value="single" {{ ($settings['payment_mode'] ?? 'single') === 'single' ? 'checked' : '' }} class="sr-only peer" onchange="togglePaymentMode()">
                        <div class="p-6 rounded-2xl border-2 transition-all duration-300 peer-checked:border-indigo-500 peer-checked:bg-indigo-50/50 dark:peer-checked:bg-indigo-900/20 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600">
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-12 h-12 rounded-xl bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 dark:text-white text-lg">Single Gateway</h3>
                                </div>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Use one payment platform for all transactions. Simple and straightforward setup.</p>
                            <div class="absolute top-4 right-4 w-6 h-6 rounded-full border-2 flex items-center justify-center peer-checked:border-indigo-500 peer-checked:bg-indigo-500 border-slate-300 dark:border-slate-600 transition-all">
                                <svg class="w-3.5 h-3.5 text-white hidden peer-checked:block" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </label>

                    <!-- Multi Gateway -->
                    <label class="relative cursor-pointer group" id="mode-multi-card">
                        <input type="radio" name="payment_mode" value="multi" {{ ($settings['payment_mode'] ?? 'single') === 'multi' ? 'checked' : '' }} class="sr-only peer" onchange="togglePaymentMode()">
                        <div class="p-6 rounded-2xl border-2 transition-all duration-300 peer-checked:border-purple-500 peer-checked:bg-purple-50/50 dark:peer-checked:bg-purple-900/20 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600">
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 dark:text-white text-lg">Multi Gateway</h3>
                                </div>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Use multiple platforms as a fallback chain. If one fails, the next one takes over.</p>
                            <div class="absolute top-4 right-4 w-6 h-6 rounded-full border-2 flex items-center justify-center peer-checked:border-purple-500 peer-checked:bg-purple-500 border-slate-300 dark:border-slate-600 transition-all">
                                <svg class="w-3.5 h-3.5 text-white hidden peer-checked:block" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Default Currency -->
                <div class="mt-6 max-w-xs">
                    <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">Default Currency</label>
                    <select name="payment_default_currency" class="mt-2 w-full px-5 py-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800 dark:text-slate-200">
                        <?php
                        $currencies = ['USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'British Pound (GBP)', 'NGN' => 'Nigerian Naira (NGN)', 'GHS' => 'Ghanaian Cedi (GHS)', 'KES' => 'Kenyan Shilling (KES)', 'ZAR' => 'South African Rand (ZAR)', 'CAD' => 'Canadian Dollar (CAD)', 'AUD' => 'Australian Dollar (AUD)', 'JPY' => 'Japanese Yen (JPY)', 'BTC' => 'Bitcoin (BTC)'];
                        foreach ($currencies as $code => $label):
                        ?>
                        <option value="{{ $code }}" {{ ($settings['payment_default_currency'] ?? 'USD') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Single Mode: Default Platform Selector -->
        <div id="single-mode-section" class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden mb-8" style="{{ ($settings['payment_mode'] ?? 'single') === 'single' ? '' : 'display:none;' }}">
            <div class="p-8 border-b border-slate-50 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30">
                <h2 class="text-xl font-bold text-slate-800 dark:text-white">Default Payment Platform</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Select the primary payment gateway for all transactions.</p>
            </div>
            <div class="p-8">
                <select name="payment_default_platform" id="defaultPlatformSelect" class="w-full max-w-md px-5 py-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800 dark:text-slate-200">
                    @foreach($platforms as $slug => $platform)
                    <option value="{{ $slug }}" {{ ($settings['payment_default_platform'] ?? 'stripe') === $slug ? 'selected' : '' }}>{{ $platform['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Multi Mode: Priority Order -->
        <div id="multi-mode-section" class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden mb-8" style="{{ ($settings['payment_mode'] ?? 'single') === 'multi' ? '' : 'display:none;' }}">
            <div class="p-8 border-b border-slate-50 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30">
                <h2 class="text-xl font-bold text-slate-800 dark:text-white">Fallback Chain Order</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Arrange enabled platforms by priority. The first platform is tried first; if it fails, the next one is used.</p>
            </div>
            <div class="p-8">
                <div class="space-y-3" id="multiPlatformList">
                    <?php
                    $multiPlatforms = array_filter(explode(',', $settings['payment_multi_platforms'] ?? ''));
                    // Show configured platforms first, then remaining
                    $orderedSlugs = !empty($multiPlatforms) ? $multiPlatforms : array_keys($platforms);
                    $remaining = array_diff(array_keys($platforms), $orderedSlugs);
                    $orderedSlugs = array_merge($orderedSlugs, $remaining);
                    $orderIndex = 1;
                    ?>
                    @foreach($orderedSlugs as $slug)
                    <?php if (!isset($platforms[$slug])) continue; $platform = $platforms[$slug]; ?>
                    <div class="flex items-center gap-4 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 multi-platform-item" data-slug="{{ $slug }}">
                        <div class="flex items-center gap-2 text-slate-400 dark:text-slate-500 cursor-grab">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                            <span class="text-xs font-bold uppercase w-6 text-center multi-order-num">{{ $orderIndex++ }}</span>
                        </div>
                        <i class="bi {{ $platform['icon'] }} text-xl text-slate-600 dark:text-slate-400"></i>
                        <span class="font-bold text-slate-800 dark:text-white flex-1">{{ $platform['name'] }}</span>
                        <span class="text-xs px-2 py-1 rounded-lg {{ ($settings["payment_{$slug}_enabled"] ?? 'false') === 'true' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' }} font-bold">
                            {{ ($settings["payment_{$slug}_enabled"] ?? 'false') === 'true' ? 'Enabled' : 'Disabled' }}
                        </span>
                        <button type="button" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300" onclick="moveUp(this)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                        </button>
                        <button type="button" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300" onclick="moveDown(this)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>
                    @endforeach
                </div>
                <input type="hidden" name="payment_multi_platforms" id="multiPlatformsInput" value="{{ $settings['payment_multi_platforms'] ?? '' }}">
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-4 font-medium">Use the arrows to reorder. Only enabled platforms will be used in the fallback chain.</p>
            </div>
        </div>

        <!-- Platform Cards -->
        <div class="mb-6">
            <h2 class="text-xl font-bold text-slate-800 dark:text-white mb-2">Payment Platforms</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Enable and configure credentials for each payment gateway.</p>
        </div>

        <div class="space-y-6" id="platformCards">
            @foreach($platforms as $slug => $platform)
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden platform-card" id="card-{{ $slug }}">
                <!-- Card Header -->
                <div class="p-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                            <i class="bi {{ $platform['icon'] }} text-2xl text-slate-700 dark:text-slate-300"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-slate-800 dark:text-white">{{ $platform['name'] }}</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $platform['description'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <!-- Enable/Disable Toggle -->
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="payment_{{ $slug }}_enabled" value="true" {{ ($settings["payment_{$slug}_enabled"] ?? 'false') === 'true' ? 'checked' : '' }} class="sr-only peer" onchange="togglePlatformCard('{{ $slug }}', this.checked)">
                            <div class="w-12 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                        <!-- Expand/Collapse -->
                        <button type="button" onclick="togglePlatformExpand('{{ $slug }}')" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                            <svg class="w-5 h-5 transition-transform expand-icon-{{ $slug }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Credential Fields (Expandable) -->
                <div id="fields-{{ $slug }}" class="border-t border-slate-100 dark:border-slate-800 p-8" style="{{ ($settings["payment_{$slug}_enabled"] ?? 'false') === 'true' ? '' : 'display:none;' }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($platform['fields'] as $fieldKey => $fieldMeta)
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">
                                {{ $fieldMeta['label'] }}
                                @if($fieldMeta['required'])
                                <span class="text-rose-500">*</span>
                                @endif
                            </label>
                            @if(($fieldMeta['type'] ?? 'text') === 'select')
                            <select name="payment_{{ $slug }}_{{ $fieldKey }}" class="w-full px-5 py-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800 dark:text-slate-200">
                                @foreach($fieldMeta['options'] as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ ($settings["payment_{$slug}_{$fieldKey}"] ?? '') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                                @endforeach
                            </select>
                            @else
                            <div class="relative">
                                <input type="{{ $fieldMeta['type'] }}" name="payment_{{ $slug }}_{{ $fieldKey }}" value="{{ $settings["payment_{$slug}_{$fieldKey}"] ?? '' }}" placeholder="{{ $fieldMeta['placeholder'] ?? '' }}" class="w-full px-5 py-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800 dark:text-slate-200 {{ $fieldMeta['type'] === 'password' ? 'pr-12' : '' }}" autocomplete="off">
                                @if($fieldMeta['type'] === 'password')
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                                    <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                </button>
                                @endif
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Save Changes Bar -->
        <div class="flex items-center justify-end gap-4 pb-20 pt-8">
            <button type="reset" class="px-8 py-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all active:scale-95">
                Discard Changes
            </button>
            <button type="submit" class="px-10 py-4 rounded-2xl gradient-bg text-white font-bold shadow-xl shadow-indigo-500/20 hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all active:scale-95 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Save Payment Settings
            </button>
        </div>
    </form>
</div>

<script>
function togglePaymentMode() {
    var mode = document.querySelector('input[name="payment_mode"]:checked').value;
    document.getElementById('single-mode-section').style.display = mode === 'single' ? '' : 'none';
    document.getElementById('multi-mode-section').style.display = mode === 'multi' ? '' : 'none';
}

function togglePlatformCard(slug, enabled) {
    var fields = document.getElementById('fields-' + slug);
    if (enabled) {
        fields.style.display = '';
    } else {
        fields.style.display = 'none';
    }
    updateMultiPlatformBadges();
}

function togglePlatformExpand(slug) {
    var fields = document.getElementById('fields-' + slug);
    var icon = document.querySelector('.expand-icon-' + slug);
    if (fields.style.display === 'none') {
        fields.style.display = '';
        icon.style.transform = 'rotate(180deg)';
    } else {
        fields.style.display = 'none';
        icon.style.transform = '';
    }
}

function togglePasswordVisibility(btn) {
    var input = btn.parentElement.querySelector('input');
    var eyeOpen = btn.querySelector('.eye-open');
    var eyeClosed = btn.querySelector('.eye-closed');
    if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.classList.add('hidden');
        eyeClosed.classList.remove('hidden');
    } else {
        input.type = 'password';
        eyeOpen.classList.remove('hidden');
        eyeClosed.classList.add('hidden');
    }
}

function moveUp(btn) {
    var item = btn.closest('.multi-platform-item');
    var prev = item.previousElementSibling;
    if (prev) {
        item.parentElement.insertBefore(item, prev);
        updateMultiOrder();
    }
}

function moveDown(btn) {
    var item = btn.closest('.multi-platform-item');
    var next = item.nextElementSibling;
    if (next) {
        item.parentElement.insertBefore(next, item);
        updateMultiOrder();
    }
}

function updateMultiOrder() {
    var items = document.querySelectorAll('.multi-platform-item');
    var slugs = [];
    items.forEach(function(item, i) {
        item.querySelector('.multi-order-num').textContent = i + 1;
        slugs.push(item.getAttribute('data-slug'));
    });
    document.getElementById('multiPlatformsInput').value = slugs.join(',');
}

function updateMultiPlatformBadges() {
    var items = document.querySelectorAll('.multi-platform-item');
    items.forEach(function(item) {
        var slug = item.getAttribute('data-slug');
        var checkbox = document.querySelector('input[name="payment_' + slug + '_enabled"]');
        var badge = item.querySelector('span:last-of-type');
        if (checkbox && checkbox.checked) {
            badge.className = 'text-xs px-2 py-1 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 font-bold';
            badge.textContent = 'Enabled';
        } else {
            badge.className = 'text-xs px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-bold';
            badge.textContent = 'Disabled';
        }
    });
}

// Initialize multi-platform order on load
document.addEventListener('DOMContentLoaded', function() {
    updateMultiOrder();
});

// Before form submit, update multi-platform order
document.getElementById('paymentSettingsForm').addEventListener('submit', function() {
    updateMultiOrder();
});
</script>
@endsection
