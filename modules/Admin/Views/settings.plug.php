@extends('admin::layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="mb-10">
    <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Application Settings</h1>
    <p class="text-slate-500 mt-1 font-medium">Configure your platform's global behavior and identity.</p>
</div>

<div class="max-w-4xl">
    <form action="{{ route('admin.settings.store') }}" method="POST">
        @csrf
        
        <div class="space-y-8">
            <!-- General Settings Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-8 border-b border-slate-50 bg-slate-50/30">
                    <h2 class="text-xl font-bold text-slate-800">General Configuration</h2>
                    <p class="text-sm text-slate-500 mt-1">Identity and contact information for your site.</p>
                </div>
                
                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Site Name</label>
                            <input type="text" name="site_name" value="{{ $settings['site_name'] }}" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Admin Email</label>
                            <input type="email" name="admin_email" value="{{ $settings['admin_email'] }}" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-slate-700 ml-1">Site Description</label>
                        <textarea name="site_description" rows="3" 
                            class="w-full px-5 py-3 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium resize-none">{{ $settings['site_description'] }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="flex items-center justify-end gap-4 pb-12">
                <button type="reset" class="px-8 py-3 rounded-2xl bg-white border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all active:scale-95">
                    Discard Changes
                </button>
                <button type="submit" class="px-8 py-3 rounded-2xl gradient-bg text-white font-bold shadow-xl shadow-indigo-500/20 hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all active:scale-95">
                    Save Configuration
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
