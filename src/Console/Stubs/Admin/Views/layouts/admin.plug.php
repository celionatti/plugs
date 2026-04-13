<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-950">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Panel') - Plugs Framework</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ url('/') }}">
    @plugcss
    <script>
        const forceDarkMode = "{{ \Modules\Admin\Models\Setting::getValue('dark_mode', 'false') }}" === 'true';
        if (forceDarkMode || localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: {{ \Modules\Admin\Models\Setting::getValue('primary_color', '#6366f1') }};
            --secondary-color: {{ \Modules\Admin\Models\Setting::getValue('secondary_color', '#4f46e5') }};
            --border-radius: {{ \Modules\Admin\Models\Setting::getValue('border_radius', '1.5rem') }};
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-outfit { font-family: 'Outfit', sans-serif; }

        /* Glass effect - using raw rgba since the engine can't handle white/opacity */
        .glass {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .dark .glass {
            background: rgba(2, 6, 23, 0.8);
        }

        /* Sidebar active link */
        .sidebar-active {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border-left: 3px solid var(--primary-color, #6366f1);
        }

        /* Sidebar hover - must be inline style or custom class since bg-white/X is broken */
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Profile card */
        .profile-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .profile-card:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); }
    </style>
    @yield('styles')
</head>

<body
    class="h-full text-slate-900 dark:text-slate-100 overflow-hidden bg-slate-50 dark:bg-slate-950">

    <div class="flex h-full overflow-hidden">
        <!-- Sidebar -->
        <aside id="adminSidebar"
            class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-950 transition-transform duration-300 ease-in-out transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0"
            style="border-right: 1px solid rgba(255,255,255,0.06);">
            <div class="flex flex-col h-full">
                <!-- Sidebar Header -->
                <div class="p-8">
                    <a href="{{ url('/') }}" class="flex items-center gap-4 group">
                        <div class="w-11 h-11 rounded-2xl flex items-center justify-center shadow-lg"
                             style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                            <span class="text-white font-black text-2xl font-outfit">P</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-xl tracking-tight text-white font-outfit leading-none">Plugs</span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em] mt-1.5">Admin Central</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 px-5 py-4 space-y-2 overflow-y-auto custom-scrollbar">
                    <div class="text-[10px] font-black text-slate-600 uppercase tracking-[0.3em] px-4 mb-4">Core Interface</div>

                    <a href="/admin" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-grid-1x2-fill opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Dashboard
                    </a>

                    <a href="/admin/users" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-people-fill opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Users
                    </a>

                    @if (is_module_enabled('Article'))
                    <a href="/admin/articles" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-journal-text opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Articles
                    </a>
                    @endif

                    <div class="pt-8 pb-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.3em] px-4 mb-2">Systems</div>

                    <a href="/admin/modules" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-puzzle-fill opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Modules
                    </a>

                    <a href="/admin/settings" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-sliders opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Settings
                    </a>

                    @if (is_module_enabled('Payment'))
                    <a href="/admin/payment" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-credit-card-fill opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Payments
                    </a>
                    @endif

                    @if (is_module_enabled('Ecommerce'))
                    <div class="pt-8 pb-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.3em] px-4 mb-2">eCommerce</div>

                    <a href="/admin/ecommerce/categories" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-list-nested opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Categories
                    </a>

                    <a href="/admin/ecommerce/brands" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-building opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Brands
                    </a>

                    <a href="/admin/ecommerce/products" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-box-seam-fill opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Products
                    </a>

                    <a href="/admin/ecommerce/orders" data-spa="true"
                       class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white group">
                        <i class="bi bi-clipboard-check-fill opacity-60 group-hover:opacity-100 transition-opacity"></i>
                        Orders
                    </a>
                    @endif
                </nav>

                <!-- Sidebar Footer -->
                <div class="p-6" style="border-top: 1px solid rgba(255,255,255,0.06);">
                    <a href="/admin/profile" data-spa="true"
                       class="profile-card flex items-center gap-4 p-4 rounded-2xl transition-all duration-300 group">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-sm">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                        <div class="flex flex-col overflow-hidden">
                            <span class="text-sm font-bold text-white truncate">{{ auth()->user()->name }}</span>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">View Profile</span>
                        </div>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
            <!-- Header -->
            <header class="h-16 glass sticky top-0 z-40 flex items-center justify-between px-4 sm:px-8"
                    style="border-bottom: 1px solid rgba(0,0,0,0.06);">
                <div class="flex items-center gap-4">
                    <button id="sidebarToggle"
                            class="lg:hidden p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-primary transition-all active:scale-95">
                        <i class="bi bi-list text-xl"></i>
                    </button>
                    <div class="hidden sm:block">
                        <h2 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em]">Dashboard</h2>
                        <span class="text-lg font-black text-slate-800 dark:text-white font-outfit">Overview</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-4">
                    <button id="theme-toggle" type="button"
                            class="p-2.5 rounded-xl text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                        <i id="theme-toggle-dark-icon" class="hidden bi bi-moon-stars-fill"></i>
                        <i id="theme-toggle-light-icon" class="hidden bi bi-brightness-high-fill"></i>
                    </button>

                    <button class="p-2.5 rounded-xl text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-800 transition-all relative">
                        <i class="bi bi-bell"></i>
                    </button>

                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="hidden sm:flex items-center gap-2 px-5 py-2 rounded-xl bg-slate-900 dark:bg-slate-800 text-white text-sm font-bold hover:bg-slate-800 dark:hover:bg-slate-700 active:scale-95 transition-all">
                            <i class="bi bi-box-arrow-right"></i>
                            Sign Out
                        </button>
                        <button type="submit" class="sm:hidden p-2.5 rounded-xl text-slate-400 hover:text-rose-500 transition-all">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </header>

            <!-- Content Area -->
            <main-content class="flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-8" flash />
        </div>
    </div>

    <!-- Sidebar Overlay for mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 backdrop-blur-sm z-40 hidden lg:hidden"
         style="background: rgba(15,23,42,0.5);"></div>

    <script>
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);
        }

        // Highlight active link
        function highlightSidebarLink() {
            const currentPath = window.location.pathname;
            const links = document.querySelectorAll('.sidebar-link');

            links.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href !== '#') {
                    const normalizedHref = href.split('?')[0].split('#')[0];
                    const normalizedPath = currentPath.split('?')[0].split('#')[0];

                    const isActive = normalizedPath === normalizedHref ||
                        (normalizedHref !== '/admin' && normalizedPath.startsWith(normalizedHref + '/'));

                    if (isActive) {
                        links.forEach(l => l.classList.remove('sidebar-active'));
                        link.classList.add('sidebar-active');
                    } else {
                        link.classList.remove('sidebar-active');
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', highlightSidebarLink);
        document.addEventListener('plugs:spa:load', highlightSidebarLink);

        var themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        var themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
        } else {
            if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
        }

        var themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function () {
                themeToggleDarkIcon.classList.toggle('hidden');
                themeToggleLightIcon.classList.toggle('hidden');

                if (localStorage.getItem('color-theme')) {
                    if (localStorage.getItem('color-theme') === 'light') {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('color-theme', 'dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('color-theme', 'light');
                    }
                } else {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('color-theme', 'light');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('color-theme', 'dark');
                    }
                }
            });
        }
    </script>
    @yield('scripts')
</body>

</html>