@extends('admin::layouts.admin')

@section('title', 'Configure Module: ' . $module['name'])

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-10 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="/admin/modules" class="w-10 h-10 rounded-full bg-white border border-slate-100 flex items-center justify-center text-slate-400 hover:text-black hover:border-black transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">{{ $module['name'] }}</h1>
                <p class="text-slate-500 mt-1 font-medium">Configure settings and view module details.</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="px-4 py-1.5 rounded-full bg-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-widest border border-slate-200">
                Version {{ $module['version'] }}
            </div>
            <div class="px-4 py-1.5 rounded-full {{ $module['enabled'] ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100' }} text-[10px] font-bold uppercase tracking-widest border">
                {{ $module['enabled'] ? 'Active' : 'Disabled' }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Sidebar: Module Info -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-3xl shadow-xl shadow-black/5 border border-slate-100 p-8">
                <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center mb-6 text-slate-400">
                    {!! $module['icon'] !!}
                </div>
                
                <h3 class="text-lg font-bold text-slate-800 mb-2">About this module</h3>
                <p class="text-sm text-slate-500 leading-relaxed mb-6">
                    {{ $module['description'] }}
                </p>
                
                <div class="space-y-4 pt-6 border-t border-slate-50">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Author</span>
                        <span class="text-sm font-bold text-slate-700">{{ $module['author'] ?? 'Unknown' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Path</span>
                        <span class="text-[10px] font-mono bg-slate-50 px-2 py-0.5 rounded text-slate-500">modules/{{ $module['name'] }}</span>
                    </div>
                </div>
            </div>

            <div class="p-6 rounded-3xl bg-indigo-600 text-white shadow-xl shadow-indigo-200/50">
                <h4 class="font-bold mb-2">Need Help?</h4>
                <p class="text-xs text-indigo-100 leading-relaxed mb-4">Check our documentation for advanced configuration patterns and module hooks.</p>
                <a href="#" class="inline-flex items-center text-xs font-bold bg-white/10 hover:bg-white/20 px-4 py-2 rounded-xl transition-colors">
                    View Docs
                    <svg class="w-3 h-3 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            </div>
        </div>

        <!-- Main Settings Section -->
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white rounded-3xl shadow-xl shadow-black/5 border border-slate-100 overflow-hidden">
                <div class="p-8 border-b border-slate-50 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Module Settings</h2>
                        <p class="text-sm text-slate-500 mt-0.5">Manage custom parameters for {{ $module['name'] }}.</p>
                    </div>
                </div>

                <div class="p-8">
                    @if(empty($settings))
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-slate-800">No Custom Settings</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs">This module doesn't have any configurable settings yet. Create a <code class="bg-slate-100 px-1 rounded">settings.json</code> file in the module folder to see them here.</p>
                        </div>
                    @else
                        <form action="/admin/modules/{{ $module['name'] }}/settings" method="POST">
                            @csrf
                            <div class="space-y-6">
                                @foreach($settings as $key => $value)
                                    <div>
                                        <label class="block text-sm font-bold text-slate-800 mb-2 capitalize">{{ str_replace('_', ' ', $key) }}</label>
                                        <input type="text" name="{{ $key }}" value="{{ $value }}" 
                                            class="w-full px-5 py-3.5 rounded-xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black transition-all font-medium">
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex items-center justify-end gap-6 pt-10 mt-10 border-t border-slate-50">
                                <button type="submit" class="px-8 py-3.5 rounded-xl bg-black text-white text-sm font-bold shadow-xl shadow-black/10 hover:scale-[1.02] active:scale-[0.98] transition-all">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Developer Tools -->
            <div class="bg-white rounded-3xl shadow-xl shadow-black/5 border border-slate-100 p-8">
                <h3 class="text-lg font-bold text-slate-800 mb-6">Developer Controls</h3>
                <div class="grid grid-cols-2 gap-4">
                    <button class="flex items-center gap-3 p-4 rounded-2xl border border-slate-100 hover:bg-slate-50 transition-colors" onclick="alert('Module optimization started...')">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center" >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div class="text-left">
                            <span class="block text-xs font-bold text-slate-800">Clear Cache</span>
                            <span class="block text-[10px] text-slate-400">Purge view & data cache</span>
                        </div>
                    </button>
                    
                    <button class="flex items-center gap-3 p-4 rounded-2xl border border-slate-100 hover:bg-slate-50 transition-colors" onclick="alert('Relinking assets...')">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 015.656 0l4-4a4 4 0 01-5.656-5.656l-1.102 1.101" />
                            </svg>
                        </div>
                        <div class="text-left">
                            <span class="block text-xs font-bold text-slate-800">Publish Assets</span>
                            <span class="block text-[10px] text-slate-400">Link public resources</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
