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
        const forceDarkMode = "{{ \App\Models\Setting::getValue('dark_mode', 'false') }}" === 'true';
        if (forceDarkMode || localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Dancing+Script:wght@400..700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: {{ \App\Models\Setting::getValue('primary_color', '#6366f1') }};
            --secondary-color: {{ \App\Models\Setting::getValue('secondary_color', '#4f46e5') }};
            --border-radius: {{ \App\Models\Setting::getValue('border_radius', '1.5rem') }};
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-outfit { font-family: 'Outfit', sans-serif; }
        
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .dark .glass {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar-active {
            background: rgba(255, 255, 255, 1) !important;
            color: #000 !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .dark .sidebar-active {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
        }
        
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); }
    </style>
    @yield('styles')
</head>

<body class="h-full text-slate-900 dark:text-slate-100 selection:bg-indigo-100 dark:selection:bg-indigo-900 selection:text-indigo-700 dark:selection:text-indigo-300 overflow-hidden bg-slate-50 dark:bg-slate-950">

    <div class="flex h-full overflow-hidden">
        <!-- Sidebar -->
        <aside id="adminSidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-950 border-r border-white/5 transition-transform duration-300 ease-in-out transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0">
            <div class="flex flex-col h-full">
                <!-- Sidebar Header -->
                <div class="p-8">
                    <a href="{{ url('/') }}" class="flex items-center gap-4 group">
                        <div class="w-11 h-11 gradient-bg rounded-2xl flex items-center justify-center transition-all duration-500 group-hover:rotate-[15deg] group-hover:scale-110 shadow-lg shadow-primary/20">
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
                    
                    <a href="/admin" data-spa="true" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white hover:bg-white/[0.03] group">
                        <i class="bi bi-grid-1x2-fill opacity-50 group-hover:opacity-100 transition-opacity"></i>
                        Dashboard
                    </a>

                    <a href="/admin/users" data-spa="true" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white hover:bg-white/[0.03] group">
                        <i class="bi bi-people-fill opacity-50 group-hover:opacity-100 transition-opacity"></i>
                        User Control
                    </a>

                    @if (is_module_enabled('Article'))
                    <a href="/admin/articles" data-spa="true" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white hover:bg-white/[0.03] group">
                        <i class="bi bi-journal-text opacity-50 group-hover:opacity-100 transition-opacity"></i>
                        Content
                    </a>
                    @endif

                    <div class="pt-8 pb-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.3em] px-4 mb-2">Systems</div>

                    <a href="/admin/modules" data-spa="true" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white hover:bg-white/[0.03] group">
                        <i class="bi bi-puzzle-fill opacity-50 group-hover:opacity-100 transition-opacity"></i>
                        Modules
                    </a>

                    <a href="/admin/settings" data-spa="true" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 text-sm font-bold rounded-2xl transition-all duration-300 text-slate-400 hover:text-white hover:bg-white/[0.03] group">
                        <i class="bi bi-sliders opacity-50 group-hover:opacity-100 transition-opacity"></i>
                        Global Settings
                    </a>
                </nav>

                <!-- Sidebar Footer -->
                <div class="p-6 border-t border-white/5">
                    <a href="/admin/profile" data-spa="true" class="flex items-center gap-4 p-4 rounded-3xl bg-white/[0.03] hover:bg-white/[0.08] transition-all duration-300 group ring-1 ring-white/5">
                        <div class="w-11 h-11 rounded-2xl bg-white flex items-center justify-center text-black font-black text-sm shadow-xl group-hover:scale-105 transition-transform">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                        <div class="flex flex-col overflow-hidden">
                            <span class="text-sm font-bold text-white truncate">{{ auth()->user()->name }}</span>
                            <span class="text-[10px] text-slate-500 font-black uppercase tracking-wider">Operator Profile</span>
                        </div>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-[#020617]">
            <!-- Header -->
            <header class="h-20 glass sticky top-0 z-40 flex items-center justify-between px-4 sm:px-8 border-b border-black/5 dark:border-white/5">
                <div class="flex items-center gap-4">
                    <button id="sidebarToggle" class="lg:hidden p-3 rounded-2xl bg-white dark:bg-slate-900 shadow-xl shadow-black/5 text-slate-600 dark:text-slate-400 hover:text-primary transition-all active:scale-90">
                        <i class="bi bi-list text-xl"></i>
                    </button>
                    <div class="hidden sm:block">
                        <h2 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[0.3em]">Platform Monitoring</h2>
                        <div class="flex items-center gap-3 mt-1">
                            <span class="text-xl font-black text-slate-900 dark:text-white font-outfit">Core Overview</span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] font-black uppercase tracking-wider ring-1 ring-emerald-500/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Live System
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-5">
                    <!-- Action Buttons -->
                    <div class="hidden md:flex items-center gap-2 pr-4 border-r border-slate-200 dark:border-white/5">
                        <button class="p-2.5 rounded-xl text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-white/[0.03] transition-all">
                            <i class="bi bi-search"></i>
                        </button>
                        <button class="p-2.5 rounded-xl text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-white/[0.03] transition-all relative">
                            <i class="bi bi-bell"></i>
                            <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-rose-500 ring-4 ring-white dark:ring-[#020617]"></span>
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <button id="theme-toggle" type="button" class="p-3 rounded-2xl text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-white/[0.03] transition-all">
                            <i id="theme-toggle-dark-icon" class="hidden bi bi-moon-stars-fill"></i>
                            <i id="theme-toggle-light-icon" class="hidden bi bi-brightness-high-fill"></i>
                        </button>

                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="hidden sm:flex items-center gap-2 px-6 py-2.5 rounded-2xl bg-slate-900 dark:bg-white text-white dark:text-black text-sm font-black hover:scale-105 active:scale-95 transition-all shadow-xl shadow-black/10">
                                <i class="bi bi-power"></i>
                                Log Out
                            </button>
                            <button type="submit" class="sm:hidden p-3 rounded-2xl bg-rose-500 text-white shadow-lg shadow-rose-500/20">
                                <i class="bi bi-power"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main-content class="flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-8" flash />
        </div>
    </div>

    <!-- Sidebar Overlay for mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden"></div>

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
                    // Normalize paths for comparison
                    const normalizedHref = href.split('?')[0].split('#')[0];
                    const normalizedPath = currentPath.split('?')[0].split('#')[0];

                    // Exact match or sub-path match (if not just /admin)
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