@extends('layouts.app')

@section('title', 'Admin - Users Management')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="glass p-8 rounded-2xl shadow-xl shadow-slate-200/50">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight brand-font gradient-text">Admin Users Management</h1>
                <p class="mt-2 text-slate-500 font-medium">Manage your system users and their roles from here.</p>
            </div>
            <button class="px-6 py-2.5 rounded-xl gradient-bg text-white font-semibold shadow-lg shadow-indigo-500/20 hover:shadow-indigo-500/40 transition-all hover:-translate-y-0.5">
                Add New User
            </button>
        </div>

        <div class="overflow-hidden bg-white/50 rounded-xl border border-slate-100">
            <div class="p-20 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 text-slate-400 mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800">No users found</h3>
                <p class="text-slate-500 mt-1">Start by adding your first administrative user.</p>
            </div>
        </div>
    </div>
</div>
@endsection
