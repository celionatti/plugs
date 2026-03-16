@extends('admin::layouts.admin')

@section('title', 'Edit User')

@section('content')
<div class="mb-10 w-full">
    <div class="flex items-center gap-3 mb-4">
        <a href="/admin/users" class="p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:text-black dark:hover:text-white hover:border-black dark:hover:border-white transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Edit User</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Update information for {{ $user->name }}.</p>
        </div>
    </div>
</div>

<div class="w-full">
    <form action="/admin/users/{{ $user->id }}" method="POST">
        @csrf
        <input type="hidden" name="_method" value="PUT">

        <div class="space-y-8">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
                <div class="p-8 border-b border-slate-50 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30 flex items-center gap-6">
                    <div class="w-16 h-16 rounded-2xl bg-black dark:bg-white flex items-center justify-center text-white dark:text-black text-xl font-bold shadow-lg shadow-black/10 dark:shadow-white/10">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800 dark:text-white">Account Information</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">User ID: {{ $user->id }}</p>
                    </div>
                </div>

                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">Full Name</label>
                            <input type="text" name="name" value="{{ $user->name }}" required
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">Email Address</label>
                            <input type="email" name="email" value="{{ $user->email }}" required
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500">
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-50 dark:border-slate-800">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-4">Change Password</h3>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">New Password (leave blank to keep current)</label>
                            <input type="password" name="password"
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500"
                                placeholder="Leave empty to keep existing password">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4">
                <a href="/admin/users" class="px-8 py-3.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all active:scale-95">
                    Cancel
                </a>
                <button type="submit" class="px-8 py-3.5 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold shadow-xl shadow-black/10 dark:shadow-white/10 hover:bg-slate-800 dark:hover:bg-slate-200 hover:-translate-y-0.5 transition-all active:scale-95">
                    Save Changes
                </button>
            </div>
        </div>
    </form>

    <!-- Danger Zone (Outside Main Form) -->
    <div class="mt-8 bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-rose-100 dark:border-rose-900/50 overflow-hidden">
        <div class="p-8 border-b border-rose-50 dark:border-rose-900/30 bg-rose-50/30 dark:bg-rose-900/10">
            <h2 class="text-lg font-bold text-rose-700 dark:text-rose-500">Danger Zone</h2>
            <p class="text-sm text-rose-400 dark:text-rose-400/80 mt-1">Irreversible actions. Proceed with caution.</p>
        </div>
        <div class="p-8 pb-12">
            <form action="/admin/users/{{ $user->id }}" method="POST" onsubmit="return confirm('Are you absolutely sure? This action cannot be undone.');" class="inline">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="px-6 py-3 rounded-2xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 font-bold text-sm hover:bg-rose-100 dark:hover:bg-rose-900/50 transition-all active:scale-95 flex items-center gap-2 border border-rose-100 dark:border-rose-800/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Delete This User
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
