<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Panel') - Plugs Framework</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ url('/') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: 'var(--primary-color)',
                        secondary: 'var(--secondary-color)',
                    }
                }
            }
        }
        
        const forceDarkMode = "{{ \App\Models\Setting::getValue('dark_mode', 'false') }}" === 'true';
        if (forceDarkMode || localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Dancing+Script:wght@400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: {{ \App\Models\Setting::getValue('primary_color', '#6366f1') }};
            --secondary-color: {{ \App\Models\Setting::getValue('secondary_color', '#4f46e5') }};
            --border-radius: {{ \App\Models\Setting::getValue('border_radius', '1.5rem') }};
        }

        /* Core Admin Styles */
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .rounded-2xl { border-radius: var(--border-radius); }
        .rounded-3xl { border-radius: calc(var(--border-radius) * 1.5); }
        
        /* Pagination Framework Base Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        .pagination-info {
            font-size: 0.875rem;
            color: #64748b; /* slate-500 */
        }
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 0.25rem;
        }
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 2.25rem;
            height: 2.25rem;
            padding: 0 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            color: #334155;
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .page-link:hover:not(.disabled) {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .active .page-link {
            background: #000000 !important;
            color: #ffffff !important;
            border-color: #000000 !important;
        }
        .disabled .page-link {
            color: #94a3b8;
            background: #f8fafc;
            border-color: #f1f5f9;
            cursor: not-allowed;
        }

        /* Dark Mode Pagination Styles */
        .dark .pagination-info {
            color: #94a3b8; /* slate-400 */
        }
        .dark .page-link {
            background: #1e293b; /* slate-800 */
            border-color: #334155; /* slate-700 */
            color: #cbd5e1; /* slate-300 */
        }
        .dark .page-link:hover:not(.disabled) {
            background: #334155;
            border-color: #475569;
        }
        .dark .active .page-link {
            background: #ffffff !important;
            color: #000000 !important;
            border-color: #ffffff !important;
        }
        .dark .disabled .page-link {
            color: #475569;
            background: #0f172a; /* slate-950 */
            border-color: #1e293b;
        }
        .brand-font {
            font-family: 'Dancing Script', cursive;
        }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .dark .glass {
            background: rgba(15, 23, 42, 0.7); /* slate-900 */
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .gradient-text {
            background: #000;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .dark .gradient-text {
            background: #fff;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .gradient-bg {
            background: var(--primary-color);
        }
        .dark .gradient-bg {
            background: #fff;
        }
        .dark .gradient-bg span {
            color: #000;
        }
        .sidebar-active {
            background: white !important;
            color: black !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .dark .sidebar-active {
            background: rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            box-shadow: none;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }
    </style>
    @yield('styles')
</head>
<body class="h-full text-slate-900 dark:text-slate-100 selection:bg-indigo-100 dark:selection:bg-indigo-900 selection:text-indigo-700 dark:selection:text-indigo-300 overflow-hidden bg-slate-50 dark:bg-slate-950">

    <div class="flex h-full">
        <!-- Sidebar -->
        <aside id="adminSidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-black border-r border-white/5 transition-transform duration-300 ease-in-out transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0">
            <div class="flex flex-col h-full bg-black">
                <!-- Sidebar Header -->
                <div class="p-6">
                    <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                        <div class="w-10 h-10 gradient-bg rounded-xl flex items-center justify-center transition-transform group-hover:rotate-12">
                            <span class="text-white font-bold text-2xl brand-font">P</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-xl tracking-tight text-white brand-font leading-none">Plugs</span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">Admin Central</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto custom-scrollbar">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-4 mb-2">Platform</div>
                    
                    <a href="/admin" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>

                    <a href="/admin/users" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Users
                    </a>

                    <a href="/admin/articles" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM14 4v4h4" />
                        </svg>
                        Articles
                    </a>

                    <div class="pt-6 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest px-4 mb-2">Management</div>

                    <a href="/admin/modules" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Modules
                    </a>

                    <a href="/admin/logs" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        System Logs
                    </a>

                    <a href="/admin/migrations" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Migrations
                    </a>

                    <a href="/admin/themes" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                        Themes
                    </a>

                    <a href="/admin/ai-prompt" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        AI Prompt
                    </a>

                    <a href="/admin/settings" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        </svg>
                        Settings
                    </a>

                    @if (class_exists(\Modules\Payment\PaymentModule::class))
                    <a href="/admin/payment" data-spa="true" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Payments
                    </a>
                    @endif

                    @if (class_exists(\Modules\Ecommerce\EcommerceModule::class))
                    <div class="pt-6 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest px-4 mb-2">
                        eCommerce</div>

                    <a href="/admin/ecommerce/categories" data-spa="true"
                        class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        Categories
                    </a>

                    <a href="/admin/ecommerce/brands" data-spa="true"
                        class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Brands
                    </a>

                    <a href="/admin/ecommerce/products" data-spa="true"
                        class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        Products
                    </a>

                    <a href="/admin/ecommerce/orders" data-spa="true"
                        class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Orders
                    </a>
                    @endif
                </nav>

                <!-- Sidebar Footer -->
                <div class="p-4 border-t border-white/5">
                    <a href="/admin/profile" data-spa="true" class="flex items-center gap-3 p-3 rounded-2xl bg-white/5 hover:bg-white/10 transition-colors group">
                        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-black font-bold text-sm">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                        <div class="flex flex-col overflow-hidden">
                            <span class="text-sm font-bold text-white truncate">{{ auth()->user()->name }}</span>
                            <span class="text-[10px] text-slate-500 font-medium truncate">View Profile</span>
                        </div>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Header -->
            <header class="h-20 glass sticky top-0 z-40 flex items-center justify-between px-8 border-b border-white/20 dark:border-white/5">
                <div class="flex items-center gap-4">
                    <button id="sidebarToggle" class="lg:hidden p-2 rounded-xl bg-white dark:bg-slate-800 shadow-sm border border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all active:scale-95">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                    </button>
                    <div class="hidden sm:block">
                        <h2 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Dashboard</h2>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-lg font-bold text-slate-800 dark:text-slate-100">Overview</span>
                            <div class="px-2 py-0.5 rounded-full bg-black dark:bg-white text-white dark:text-black text-[10px] font-bold uppercase tracking-wider">Active</div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button id="theme-toggle" type="button" class="p-2.5 rounded-xl text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-800 dark:hover:text-indigo-400 transition-all">
                        <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
                    </button>
                    <button class="p-2.5 rounded-xl text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-indigo-400 transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </button>
                    <div class="h-8 w-px bg-slate-200 dark:bg-slate-700 mx-2"></div>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-sm font-bold hover:bg-rose-50 dark:hover:bg-rose-900/30 hover:text-rose-600 dark:hover:text-rose-400 transition-all active:scale-95">
                            Sign Out
                        </button>
                    </form>
                </div>
            </header>

            <!-- Content Area -->
            <main class="flex-1 overflow-y-auto custom-scrollbar p-8">
                <!-- Flash Messages -->
                {!! \Plugs\Utils\FlashMessage::render() !!}

                @yield('content')
            </main>
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
        const currentPath = window.location.pathname;
        const links = document.querySelectorAll('.sidebar-link');
        links.forEach(link => {
            if (link.getAttribute('href') !== '#' && currentPath.includes(link.getAttribute('href'))) {
                links.forEach(l => l.classList.remove('sidebar-active', 'text-white'));
                link.classList.add('sidebar-active');
            }
        });

        var themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        var themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            themeToggleLightIcon.classList.remove('hidden');
        } else {
            themeToggleDarkIcon.classList.remove('hidden');
        }

        var themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
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
    <script src="{{ url('/plugs/plugs-spa.js') }}"></script>
    <script src="{{ url('/plugs/plugs-lazy.js') }}"></script>
    <script>
        window.plugsSPA = new PlugsSPA({
            contentSelector: 'main',
            loaderClass: 'spa-loading'
        });
    </script>
</body>
</html>
