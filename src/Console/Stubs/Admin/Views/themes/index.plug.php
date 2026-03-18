@extends('admin::layouts.admin')

@section('title', 'Themes')

@section('content')
<div class="mb-10">
    <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Themes</h1>
    <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Manage the look and feel of your application. Themes override default views when activated.</p>
</div>

<!-- Active Theme Banner -->
<div class="mb-8 p-6 rounded-3xl bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 border border-indigo-100 dark:border-indigo-800/40">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl gradient-bg flex items-center justify-center shadow-lg shadow-indigo-500/20">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
            </svg>
        </div>
        <div>
            <p class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">Active Theme</p>
            <p class="text-xl font-extrabold text-slate-800 dark:text-white mt-0.5">
                {{ ucfirst($activeTheme) }}
            </p>
        </div>
    </div>
</div>

<!-- Theme Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 pb-20">
    @foreach($themes as $slug => $theme)
    <div class="group relative bg-white dark:bg-slate-900 rounded-3xl shadow-sm border {{ $theme['active'] ? 'border-indigo-300 dark:border-indigo-600 ring-2 ring-indigo-500/20' : 'border-slate-100 dark:border-slate-800' }} overflow-hidden transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
        <!-- Theme Preview Area -->
        <div class="relative h-44 bg-gradient-to-br {{ $theme['active'] ? 'from-indigo-100 via-purple-50 to-violet-100 dark:from-indigo-900/40 dark:via-purple-900/30 dark:to-violet-900/40' : 'from-slate-100 via-slate-50 to-slate-100 dark:from-slate-800 dark:via-slate-800/50 dark:to-slate-800' }} flex items-center justify-center overflow-hidden">
            @if(!empty($theme['screenshot']) && file_exists($theme['screenshot']))
            <!-- Actual Screenshot -->
            <img src="/admin/themes/{{ $slug }}/screenshot" alt="{{ $theme['name'] }}" class="w-full h-full object-cover">
            @elseif($slug === 'default')
            <!-- Default theme illustration -->
            <div class="flex flex-col items-center gap-3 opacity-60">
                <svg class="w-16 h-16 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                </svg>
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Built-in</span>
            </div>
            @else
            <!-- Custom theme illustration -->
            <div class="flex flex-col items-center gap-3 opacity-60">
                <svg class="w-16 h-16 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                </svg>
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Custom Theme</span>
            </div>
            @endif

            <!-- Active Badge -->
            @if($theme['active'])
            <div class="absolute top-4 right-4">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-500/30">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    Active
                </span>
            </div>
            @endif
        </div>

        <!-- Theme Info -->
        <div class="p-6">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">{{ $theme['name'] }}</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500 font-semibold mt-0.5">
                        by {{ $theme['author'] }} &middot; v{{ $theme['version'] }}
                    </p>
                </div>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 font-medium leading-relaxed mb-5 line-clamp-2">
                {{ $theme['description'] }}
            </p>

            <!-- Tags -->
            @if(!empty($theme['tags']))
            <div class="flex flex-wrap gap-1.5 mb-5">
                @foreach($theme['tags'] as $tag)
                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $tag }}</span>
                @endforeach
            </div>
            @endif

            <!-- Action -->
            @if($theme['active'])
            <div class="flex items-center gap-2 text-sm font-bold text-indigo-600 dark:text-indigo-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Currently Active
            </div>
            @else
            <form action="/admin/themes/{{ $slug }}/activate" method="POST">
                @csrf
                <button type="submit" class="w-full px-5 py-3 rounded-2xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-sm font-bold hover:bg-slate-800 dark:hover:bg-slate-100 transition-all active:scale-[0.98] shadow-sm hover:shadow-md flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                    </svg>
                    Activate Theme
                </button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>

<!-- How Themes Work Info -->
<div class="fixed bottom-6 right-8 z-30">
    <details class="group">
        <summary class="cursor-pointer list-none">
            <div class="w-12 h-12 rounded-2xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center shadow-xl hover:shadow-2xl transition-all hover:scale-105">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </summary>
        <div class="absolute bottom-16 right-0 w-80 p-6 rounded-3xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-100 dark:border-slate-800 text-sm">
            <h4 class="font-bold text-slate-800 dark:text-white mb-3">How Themes Work</h4>
            <div class="space-y-2 text-slate-500 dark:text-slate-400 leading-relaxed">
                <p>Themes override specific views by placing files in <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-xs font-mono">resources/views/themes/{name}/</code>.</p>
                <p>Any view file placed in your theme directory will take priority over the default view. Unoverridden views automatically fall back to the defaults.</p>
                <p>Add a <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-xs font-mono">theme.json</code> in your theme folder to set metadata like name, author, and description.</p>
            </div>
        </div>
    </details>
</div>
@endsection
