@extends('admin::layouts.admin')

@section('title', 'Platform Settings')

@section('content')
<div class="mb-10 w-full">
    <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Platform Settings</h1>
    <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Configure and customize your application's appearance and behavior.</p>
</div>

<form action="/admin/settings" method="POST">
    @csrf
    
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <!-- Sidebar Navigation -->
        <div class="xl:col-span-1 space-y-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-4 sticky top-28">
                <nav class="space-y-1">
                    <button type="button" class="w-full flex items-center gap-3 px-5 py-4 text-sm font-bold rounded-2xl bg-black dark:bg-white text-white dark:text-black shadow-lg shadow-black/10 dark:shadow-white/10 transition-all">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                        </svg>
                        Appearance
                    </button>
                    <button type="button" class="w-full flex items-center gap-3 px-5 py-4 text-sm font-bold rounded-2xl text-slate-500 hover:text-black dark:hover:text-white hover:bg-slate-50 dark:hover:bg-white/5 transition-all">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Security
                    </button>
                    <button type="button" class="w-full flex items-center gap-3 px-5 py-4 text-sm font-bold rounded-2xl text-slate-500 hover:text-black dark:hover:text-white hover:bg-slate-50 dark:hover:bg-white/5 transition-all">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        Notifications
                    </button>
                </nav>
                
                <div class="mt-6 pt-6 border-t border-slate-50 dark:border-slate-800">
                    <button type="submit" class="w-full px-6 py-4 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold shadow-xl shadow-black/10 dark:shadow-white/10 hover:bg-slate-800 dark:hover:bg-slate-200 transition-all active:scale-95 flex items-center justify-center gap-2 group">
                        <svg class="w-5 h-5 transition-transform group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        Save Settings
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Settings Content -->
        <div class="xl:col-span-2 space-y-8">
            <!-- Appearance Section -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
                <div class="p-8 border-b border-slate-50 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30">
                    <h2 class="text-xl font-extrabold text-slate-800 dark:text-white">Appearance</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">Control the visual style of your admin panel.</p>
                </div>
                
                <div class="p-8 space-y-8">
                    <!-- Theme Mode -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Interface Mode</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Force a specific color theme.</p>
                        </div>
                        <div class="md:col-span-2">
                             <div class="relative">
                                <select name="settings[dark_mode]" class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium appearance-none cursor-pointer bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5%22%20stroke%3D%22%2394a3b8%22%20stroke-width%3D%222%22%20fill%3D%22none%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:right_1.25rem_center]">
                                    <option value="false" {{ ($settings['dark_mode'] ?? 'false') === 'false' ? 'selected' : '' }}>System / Light Mode</option>
                                    <option value="true" {{ ($settings['dark_mode'] ?? 'false') === 'true' ? 'selected' : '' }}>Force Dark Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="h-px bg-slate-50 dark:bg-slate-800"></div>

                    <!-- Colors -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Brand Palette</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Primary and secondary colors.</p>
                        </div>
                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 px-1">Primary Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="settings[primary_color]" value="{{ $settings['primary_color'] ?? '#000000' }}" class="w-12 h-12 rounded-xl border-0 p-0 overflow-hidden cursor-pointer shadow-sm">
                                    <input type="text" value="{{ $settings['primary_color'] ?? '#000000' }}" readonly class="flex-1 px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border-none text-xs font-mono font-bold text-slate-500 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 px-1">Secondary Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="settings[secondary_color]" value="{{ $settings['secondary_color'] ?? '#4f46e5' }}" class="w-12 h-12 rounded-xl border-0 p-0 overflow-hidden cursor-pointer shadow-sm">
                                    <input type="text" value="{{ $settings['secondary_color'] ?? '#4f46e5' }}" readonly class="flex-1 px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border-none text-xs font-mono font-bold text-slate-500 uppercase">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="h-px bg-slate-50 dark:bg-slate-800"></div>

                    <!-- Layout -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">System Feel</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Corner styles and spacing.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 px-1">Corner Radius</label>
                            <div class="relative">
                                <select name="settings[border_radius]" class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium appearance-none cursor-pointer bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5%22%20stroke%3D%22%2394a3b8%22%20stroke-width%3D%222%22%20fill%3D%22none%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:right_1.25rem_center]">
                                    <option value="0.5rem" {{ ($settings['border_radius'] ?? '1.5rem') === '0.5rem' ? 'selected' : '' }}>Boxy (8px)</option>
                                    <option value="1rem" {{ ($settings['border_radius'] ?? '1.5rem') === '1rem' ? 'selected' : '' }}>Modern (16px)</option>
                                    <option value="1.5rem" {{ ($settings['border_radius'] ?? '1.5rem') === '1.5rem' ? 'selected' : '' }}>Playful (24px)</option>
                                    <option value="2rem" {{ ($settings['border_radius'] ?? '1.5rem') === '2rem' ? 'selected' : '' }}>Round (32px)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
