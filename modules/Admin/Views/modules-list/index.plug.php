@extends('admin::layouts.admin')

@section('title', 'Modules Management')

@section('content')
<div class="mb-10">
    <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Feature Modules</h1>
    <p class="text-slate-500 mt-1 font-medium">Manage and extend your platform's capabilities through modular plugs.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    @foreach($modules as $module)
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-xl hover:shadow-indigo-500/5 transition-all group">
        <div class="p-8">
            <div class="flex items-start justify-between mb-6">
                <div class="w-14 h-14 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-black group-hover:text-white transition-all">
                    @if($module['name'] === 'Admin')
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    @else
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    @endif
                </div>
                <div class="flex flex-col items-end">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">v{{ $module['version'] }}</span>
                    @if($module['enabled'])
                    <span class="mt-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-600 text-[9px] font-bold uppercase tracking-wider">Active</span>
                    @else
                    <span class="mt-1 px-2 py-0.5 rounded-md bg-slate-100 text-slate-400 text-[9px] font-bold uppercase tracking-wider">Disabled</span>
                    @endif
                </div>
            </div>
            
            <h2 class="text-xl font-bold text-slate-800 mb-2">{{ $module['name'] }}</h2>
            <p class="text-sm text-slate-500 line-clamp-2 mb-6 font-medium leading-relaxed">
                {{ $module['description'] }}
            </p>

            <div class="flex items-center justify-between pt-6 border-t border-slate-50">
                <form action="/admin/modules/{{ $module['name'] }}/toggle" method="POST">
                    @csrf
                    <button type="submit" class="text-xs font-bold {{ $module['enabled'] ? 'text-amber-600' : 'text-emerald-600' }} hover:opacity-80 transition-colors uppercase tracking-widest border-b border-black/20 hover:border-black transition-all pb-0.5">
                        {{ $module['enabled'] ? 'Disable' : 'Enable' }}
                    </button>
                </form>
                
                <div class="flex items-center gap-4">
                    <a href="/admin/modules/{{ $module['name'] }}/configure" class="text-xs font-bold text-black hover:text-slate-600 transition-colors uppercase tracking-widest border-b border-black/20 hover:border-black transition-all pb-0.5">Configure</a>
                    
                    @if($module['name'] !== 'Admin')
                    <form action="/admin/modules/{{ $module['name'] }}/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this module? This action cannot be undone.')">
                        @csrf
                        <button type="submit" class="text-xs font-bold text-red-500 hover:text-red-700 transition-colors uppercase tracking-widest border-b border-red-200 hover:border-red-500 transition-all pb-0.5">Delete</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach

    <!-- Add New Module Placeholder -->
    <a href="/admin/modules/create" class="bg-white rounded-3xl shadow-sm border border-slate-100 border-dashed border-2 flex flex-col items-center justify-center p-8 hover:border-indigo-200 hover:bg-slate-50/50 transition-all group">
        <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
        </div>
        <span class="text-sm font-bold text-slate-400">Install Module</span>
    </a>
</div>
@endsection
