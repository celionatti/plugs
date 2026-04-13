@extends('admin::layouts.admin')

@section('title', 'Create New Module')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-10 flex items-center gap-4">
        <a href="/admin/modules" class="w-10 h-10 rounded-full bg-white border border-slate-100 flex items-center justify-center text-slate-400 hover:text-black hover:border-black transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Scaffold New Module</h1>
            <p class="text-slate-500 mt-1 font-medium">Generate a new plug-and-play feature module instantly.</p>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-xl shadow-black border border-slate-100 p-10">
        <form action="/admin/modules" method="POST">
            @csrf
            <div class="space-y-8">
                <!-- Module Name -->
                <div>
                    <label for="name" class="block text-sm font-bold text-slate-800 mb-3 tracking-wide">Module Name</label>
                    <div class="relative group">
                        <input type="text" name="name" id="name" placeholder="e.g. Payments, Analytics, Blog" 
                            class="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200 focus:border-black transition-all font-medium" 
                            required>
                        <div class="absolute inset-y-0 right-0 pr-6 flex items-center pointer-events-none text-slate-300 group-focus-within:text-black transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-[11px] text-slate-400 font-medium">Use StudlyCase for the name. We'll automatically append "Module" and scaffold the directory structure.</p>
                </div>

                <!-- Stub Info -->
                <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100 border-dashed">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest mb-4">What will be generated?</h4>
                    <ul class="space-y-3">
                        <li class="flex items-center gap-3 text-xs text-slate-500 font-medium">
                            <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Directory: <code class="bg-white px-2 py-0.5 rounded border border-slate-100">modules/@{Name}</code>
                        </li>
                        <li class="flex items-center gap-3 text-xs text-slate-500 font-medium">
                            <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Main Class: <code class="bg-white px-2 py-0.5 rounded border border-slate-100">@{Name}Module.php</code>
                        </li>
                        <li class="flex items-center gap-3 text-xs text-slate-500 font-medium">
                            <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            File: <code class="bg-white px-2 py-0.5 rounded border border-slate-100">settings.json</code> (Configuration)
                        </li>
                        <li class="flex items-center gap-3 text-xs text-slate-500 font-medium">
                            <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Folders: <code class="bg-white px-2 py-0.5 rounded border border-slate-100">Controllers</code>, <code class="bg-white px-2 py-0.5 rounded border border-slate-100">Views</code>, <code class="bg-white px-2 py-0.5 rounded border border-slate-100">Routes</code>
                        </li>
                    </ul>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-6 pt-6 border-t border-slate-50">
                    <a href="/admin/modules" class="text-xs font-bold text-slate-400 hover:text-black transition-colors uppercase tracking-widest">Cancel</a>
                    <button type="submit" class="px-10 py-4 rounded-2xl bg-black text-white text-sm font-bold shadow-xl shadow-slate-300 hover:scale-[1.02] active:scale-[0.98] transition-all">
                        Build Module
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
