@extends('admin::layouts.admin')

@section('title', 'My Profile')

@section('content')
<div class="mb-10">
    <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Personal Profile</h1>
    <p class="text-slate-500 mt-1 font-medium">Manage your personal information and security settings.</p>
</div>

<div class="max-w-4xl">
    <form action="{{ route('admin.profile.update') }}" method="POST">
        @csrf
        
        <div class="space-y-8">
            <!-- Account Info Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-8 border-b border-slate-50 bg-slate-50/30 flex items-center gap-6">
                    <div class="w-20 h-20 rounded-3xl gradient-bg flex items-center justify-center text-white text-2xl font-bold shadow-xl shadow-indigo-500/20">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Account Information</h2>
                        <p class="text-sm text-slate-500 mt-0.5">Your personal details and identification.</p>
                    </div>
                </div>
                
                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Full Name</label>
                            <input type="text" name="name" value="{{ $user->name }}" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Email Address</label>
                            <input type="email" name="email" value="{{ $user->email }}" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium">
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-50">
                        <h3 class="text-sm font-bold text-slate-800 mb-4">Security</h3>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">New Password (leave blank to keep current)</label>
                            <input type="password" name="password" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="flex items-center justify-end gap-4 pb-12">
                <button type="submit" class="px-8 py-3 rounded-2xl gradient-bg text-white font-bold shadow-xl shadow-indigo-500/20 hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all active:scale-95">
                    Update Profile
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
