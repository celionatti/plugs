@extends('admin::layouts.admin')

@section('title', 'User Details')

@section('content')
<div class="mb-10">
    <div class="flex items-center gap-3 mb-4">
        <a href="/admin/users" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-primary hover:border-indigo-200 transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">User Details</h1>
            <p class="text-slate-500 mt-1 font-medium">Viewing profile information for {{ $user->name }}.</p>
        </div>
    </div>
</div>

<div class="w-full">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 border-b border-slate-50 bg-slate-50 flex items-center gap-6">
            <div class="w-20 h-20 rounded-3xl bg-black flex items-center justify-center text-white text-2xl font-bold shadow-xl shadow-slate-300">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-2xl font-bold text-slate-800">{{ $user->name }}</h2>
                <p class="text-sm text-slate-500 mt-1 font-medium">{{ $user->email }}</p>
            </div>
        </div>

        <div class="p-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">User ID</div>
                    <div class="text-lg font-bold text-slate-800">{{ $user->id }}</div>
                </div>
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Joined</div>
                    <div class="text-lg font-bold text-slate-800">{{ $user->created_at ?? 'N/A' }}</div>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Email Verified</div>
                <div class="text-lg font-bold text-slate-800">{{ $user->email_verified_at ? 'Yes' : 'Not Verified' }}</div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-4 mt-8 pb-12">
        <a href="/admin/users" class="px-8 py-3.5 rounded-2xl bg-white border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all active:scale-95">
            Back to Users
        </a>
        <a href="/admin/users/{{ $user->id }}/edit" class="px-8 py-3.5 rounded-2xl bg-black text-white font-bold hover:bg-slate-800 shadow-xl shadow-slate-300 transition-all active:scale-95">
            Edit User
        </a>
        <form action="/admin/users/{{ $user->id }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" class="inline">
            @csrf
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="px-8 py-3.5 rounded-2xl bg-white border border-rose-100 text-rose-600 font-bold hover:bg-rose-50 transition-all active:scale-95">
                Delete User
            </button>
        </form>
    </div>
</div>
@endsection
