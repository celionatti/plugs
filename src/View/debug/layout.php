<!DOCTYPE html>
<?php /** @var string $childContent */ ?>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ Plugs Profiler | High-Performance Debugger</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-deep: #050810;
            --bg-glass: rgba(15, 23, 42, 0.6);
            --bg-glass-heavy: rgba(15, 23, 42, 0.85);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-glass-bright: rgba(255, 255, 255, 0.15);

            --accent: #a855f7;
            --accent-glow: rgba(168, 85, 247, 0.4);
            --secondary: #3b82f6;
            --secondary-glow: rgba(59, 130, 246, 0.4);

            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;

            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0ea5e9;

            --ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-deep);
            background-image:
                radial-gradient(circle at 10% 10%, rgba(168, 85, 247, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(59, 130, 246, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 50% 50%, rgba(15, 23, 42, 1) 0%, transparent 100%);
            background-attachment: fixed;
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* Particles/Grid Effect */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(var(--border-glass) 1px, transparent 1px);
            background-size: 30px 30px;
            pointer-events: none;
            z-index: -1;
            opacity: 0.3;
        }

        .plugs-dbg-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2.5rem 2rem;
        }

        /* Glass Components */
        .plugs-dbg-glass-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            transition: border-color 0.3s ease;
        }

        .plugs-dbg-glass-panel:hover {
            border-color: var(--border-glass-bright);
        }

        /* Header Styling */
        .plugs-dbg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
        }

        .plugs-dbg-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: inherit;
        }

        .plugs-dbg-brand-logo {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--accent), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px var(--accent-glow);
            transition: transform 0.5s var(--ease-out-expo);
        }

        .plugs-dbg-brand:hover .plugs-dbg-brand-logo {
            transform: rotate(10deg) scale(1.1);
        }

        .plugs-dbg-brand-name {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Navigation Buttons */
        .plugs-dbg-nav-actions {
            display: flex;
            gap: 0.75rem;
        }

        .plugs-dbg-btn {
            padding: 0.6rem 1.25rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s var(--ease-out-expo);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .plugs-dbg-btn-primary {
            background: linear-gradient(to right, var(--accent), var(--secondary));
            color: white;
            border: none;
        }

        .plugs-dbg-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px var(--accent-glow);
        }

        .plugs-dbg-btn-glass {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        .plugs-dbg-btn-glass:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--border-glass-bright);
        }

        /* Typography & Content */
        h1,
        h2,
        h3,
        h4 {
            color: var(--text-primary);
            font-weight: 700;
        }

        p {
            color: var(--text-secondary);
        }

        .plugs-dbg-animate-fade-up {
            animation: plugsDbgFadeUp 0.6s var(--ease-out-expo) both;
        }

        @keyframes plugsDbgFadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Profile Row Special Hover */
        .plugs-dbg-profile-row {
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .plugs-dbg-profile-row:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        /* Scoped styles for child views */
        .plugs-dbg-card {
            margin-bottom: 2rem;
        }

        /* Badges */
        .plugs-dbg-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Tabs System */
        .plugs-dbg-tab-content {
            display: none !important;
        }

        .plugs-dbg-tab-content.active {
            display: block !important;
            animation: plugsDbgFadeUp 0.3s var(--ease-out-expo) both;
        }

        .plugs-dbg-tabs-header {
            display: flex;
            gap: 2rem;
            padding: 0 2rem;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--border-glass);
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .plugs-dbg-tabs-header::-webkit-scrollbar {
            display: none;
        }

        .plugs-dbg-tab-btn {
            background: none !important;
            border: none !important;
            padding: 1.25rem 1.5rem;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
            font-family: 'Outfit', sans-serif !important;
            outline: none !important;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: 8px;
            margin: 0.5rem 0;
        }

        .plugs-dbg-tab-btn:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05) !important;
        }

        .plugs-dbg-tab-btn.active {
            color: var(--accent);
            background: rgba(168, 85, 247, 0.1) !important;
        }

        .plugs-dbg-tab-btn .plugs-dbg-tab-icon {
            opacity: 0.7;
            transition: transform 0.3s;
        }

        .plugs-dbg-tab-btn:hover .plugs-dbg-tab-icon {
            opacity: 1;
            transform: scale(1.1);
        }

        .plugs-dbg-tab-btn.active .plugs-dbg-tab-icon {
            opacity: 1;
            color: var(--accent);
        }

        .plugs-dbg-tab-btn::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--accent);
            box-shadow: 0 0 12px var(--accent-glow);
            transition: all 0.3s ease;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .plugs-dbg-tab-btn.active::after {
            width: calc(100% - 2rem);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-deep);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-glass-bright);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        @media (max-width: 768px) {
            .plugs-dbg-container {
                padding: 1.5rem 1rem;
            }

            .plugs-dbg-header {
                flex-direction: column;
                gap: 1.5rem;
                align-items: flex-start;
            }

            .plugs-dbg-nav-actions {
                width: 100%;
            }

            .plugs-dbg-nav-actions .plugs-dbg-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="plugs-dbg-container">
        <header class="plugs-dbg-header plugs-dbg-animate-fade-up">
            <a href="/debug/performance" class="plugs-dbg-brand">
                <div class="plugs-dbg-brand-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round" style="color: white;">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                </div>
                <span class="plugs-dbg-brand-name">Plugs Profiler</span>
            </a>
            <nav class="plugs-dbg-nav-actions">
                <a href="/debug/performance" class="plugs-dbg-btn plugs-dbg-btn-glass">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                    </svg>
                    Requests
                </a>
                <a href="/debug/performance/latest" class="plugs-dbg-btn plugs-dbg-btn-primary">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Latest
                </a>
            </nav>
        </header>

        <main class="plugs-dbg-animate-fade-up" style="animation-delay: 0.1s;">
            <?= $childContent ?>
        </main>
    </div>

    <?php
    $nonce = function_exists('asset_manager') ? asset_manager()->getNonce() : null;
    $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
    ?>
    <script<?= $nonceAttr ?>>
        function plugsSwitchTab(btn, id) {
            const container = btn.closest('.plugs-dbg-glass-panel, .plugs-dbg-card, .plugs-dbg-container,
                .plugs - debug - wrapper, #plugs - profiler - modal');
        if (!container) return;

            // Find elements specifically within this tab context
            const allTabs = container.querySelectorAll('.plugs-dbg-tab-content');
            const allBtns = container.querySelectorAll('.plugs-dbg-tab-btn, .nav-item');

            allTabs.forEach(el => el.classList.remove('active'));
            allBtns.forEach(el => el.classList.remove('active'));

            const target = container.querySelector('#' + id);
            if (target) {
                target.classList.add('active');
            }
            btn.classList.add('active');
        }
        window.plugsSwitchTab = plugsSwitchTab;

        // Initialize any active tabs & Event Delegation
        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('click', function (e) {
                const tabBtn = e.target.closest('.plugs-dbg-tab-btn, .nav-item[data-tab]');
                if (tabBtn) {
                    const id = tabBtn.getAttribute('data-tab');
                    if (id) {
                        plugsSwitchTab(tabBtn, id);
                        e.preventDefault();
                    }
                }

                // Profiler Row Click
                const profileRow = e.target.closest('.plugs-dbg-profile-row[data-href]');
                if (profileRow) {
                    window.location.href = profileRow.getAttribute('data-href');
                }
            });

            // Auto-init active tabs based on data-tab
            document.querySelectorAll('.nav-item.active[data-tab], .plugs-dbg-tab-btn.active[data-tab]').forEach(btn => {
                const id = btn.getAttribute('data-tab');
                if (id) plugsSwitchTab(btn, id);
            });
        });
    </script>
</body>

</html>