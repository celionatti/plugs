@extends('admin::layouts.admin')

@section('title', 'Edit User')

@section('content')
<div class="mb-10">
    <div class="flex items-center gap-3 mb-4">
        <a href="/admin/users" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-black hover:border-black transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Edit User</h1>
            <p class="text-slate-500 mt-1 font-medium">Update information for {{ $user->name }}.</p>
        </div>
    </div>
</div>

<div class="max-w-3xl">
    <form action="/admin/users/{{ $user->id }}" method="POST">
        @csrf
        <input type="hidden" name="_method" value="PUT">

        <div class="space-y-8">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-8 border-b border-slate-50 bg-slate-50/30 flex items-center gap-6">
                    <div class="w-16 h-16 rounded-2xl bg-black flex items-center justify-center text-white text-xl font-bold shadow-lg shadow-black/10">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Account Information</h2>
                        <p class="text-sm text-slate-500 mt-0.5">User ID: {{ $user->id }}</p>
                    </div>
                </div>

                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Full Name</label>
                            <input type="text" name="name" value="{{ $user->name }}" required
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-4 focus:ring-black/5 focus:border-black transition-all font-medium">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Email Address</label>
                            <input type="email" name="email" value="{{ $user->email }}" required
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-4 focus:ring-black/5 focus:border-black transition-all font-medium">
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-50">
                        <h3 class="text-sm font-bold text-slate-800 mb-4">Change Password</h3>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">New Password (leave blank to keep current)</label>
                            <input type="password" name="password"
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 focus:outline-none focus:ring-4 focus:ring-black/5 focus:border-black transition-all font-medium"
                                placeholder="Leave empty to keep existing password">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4">
                <a href="/admin/users" class="px-8 py-3.5 rounded-2xl bg-white border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all active:scale-95">
                    Cancel
                </a>
                <button type="submit" class="px-8 py-3.5 rounded-2xl bg-black text-white font-bold shadow-xl shadow-black/10 hover:bg-slate-800 hover:-translate-y-0.5 transition-all active:scale-95">
                    Save Changes
                </button>
            </div>
        </div>
    </form>

    <!-- Danger Zone (Outside Main Form) -->
    <div class="mt-8 bg-white rounded-3xl shadow-sm border border-rose-100 overflow-hidden">
        <div class="p-8 border-b border-rose-50 bg-rose-50/30">
            <h2 class="text-lg font-bold text-rose-700">Danger Zone</h2>
            <p class="text-sm text-rose-400 mt-1">Irreversible actions. Proceed with caution.</p>
        </div>
        <div class="p-8 pb-12">
            <form action="/admin/users/{{ $user->id }}" method="POST" onsubmit="return confirm('Are you absolutely sure? This action cannot be undone.');" class="inline">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="px-6 py-3 rounded-2xl bg-rose-50 text-rose-600 font-bold text-sm hover:bg-rose-100 transition-all active:scale-95 flex items-center gap-2">
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
