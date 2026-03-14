<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Panel') - Plugs Framework</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Dancing+Script:wght@400..700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
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
        .gradient-text {
            background: #000;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .gradient-bg {
            background: #000;
        }
        .sidebar-active {
            background: white !important;
            color: black !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
<body class="h-full text-slate-900 selection:bg-indigo-100 selection:text-indigo-700 overflow-hidden">

    <div class="flex h-full">
        <!-- Sidebar -->
        <aside id="adminSidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-black border-r border-white/5 transition-transform duration-300 ease-in-out transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0">
            <div class="flex flex-col h-full bg-black">
                <!-- Sidebar Header -->
                <div class="p-6">
                    <a href="{{ route('welcome') }}" class="flex items-center gap-3 group">
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
                    
                    <a href="/admin" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group sidebar-active">
                        <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>

                    <a href="/admin/users" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Users
                    </a>

                    <div class="pt-6 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest px-4 mb-2">Management</div>

                    <a href="/admin/modules" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Modules
                    </a>

                    <a href="/admin/settings" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        </svg>
                        Settings
                    </a>
                </nav>

                <!-- Sidebar Footer -->
                <div class="p-4 border-t border-white/5">
                    <a href="/admin/profile" class="flex items-center gap-3 p-3 rounded-2xl bg-white/5 hover:bg-white/10 transition-colors group">
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
            <header class="h-20 glass sticky top-0 z-40 flex items-center justify-between px-8 border-b border-white/20">
                <div class="flex items-center gap-4">
                    <button id="sidebarToggle" class="lg:hidden p-2 rounded-xl bg-white shadow-sm border border-slate-100 text-slate-600 hover:text-indigo-600 transition-all active:scale-95">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                    </button>
                    <div class="hidden sm:block">
                        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Dashboard</h2>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-lg font-bold text-slate-800">Overview</span>
                            <div class="px-2 py-0.5 rounded-full bg-black text-white text-[10px] font-bold uppercase tracking-wider">Active</div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button class="p-2.5 rounded-xl text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </button>
                    <div class="h-8 w-px bg-slate-200 mx-2"></div>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-sm font-bold hover:bg-rose-50 hover:text-rose-600 transition-all active:scale-95">
                            Sign Out
                        </button>
                    </form>
                </div>
            </header>

            <!-- Content Area -->
            <main class="flex-1 overflow-y-auto custom-scrollbar p-8">
                @if(session()->has('success'))
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm font-semibold flex items-center gap-3 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    {{ session()->get('success') }}
                </div>
                @endif

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
    </script>
    @yield('scripts')
</body>
</html>
