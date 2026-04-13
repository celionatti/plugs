@extends('admin::layouts.admin')

@section('title', 'Create User')

@section('content')
<div class="mb-10 w-full">
    <div class="flex items-center gap-3 mb-2">
        <a href="/admin/users" class="text-slate-400 hover:text-primary dark:hover:text-indigo-400 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Create Member</h1>
    </div>
    <p class="text-slate-500 dark:text-slate-400 font-medium">Add a new user to your platform.</p>
</div>

<div class="max-w-3xl">
    <form action="/admin/users" method="POST" class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-8 space-y-6">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="name" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2 px-1">Full Name</label>
                <input type="text" id="name" name="name" required placeholder="John Doe" class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-slate-200 dark:focus:ring-slate-800 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500">
            </div>
            <div>
                <label for="email" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2 px-1">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="john@example.com" class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-slate-200 dark:focus:ring-slate-800 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500">
            </div>
        </div>

        <div>
            <label for="password" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2 px-1">Initial Password</label>
            <input type="password" id="password" name="password" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-slate-200 dark:focus:ring-slate-800 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500">
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-8 py-4 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold shadow-xl shadow-slate-300 dark:shadow-white hover:bg-slate-800 dark:hover:bg-slate-200 transition-all active:scale-95">
                Create Account
            </button>
            <a href="/admin/users" class="px-8 py-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-100 dark:hover:bg-slate-750 transition-all active:scale-95">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
