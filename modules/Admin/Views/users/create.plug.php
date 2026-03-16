@extends('admin::layouts.admin')

@section('title', 'Create User')

@section('content')
<div class="mb-10 w-full">
    <div class="flex items-center gap-3 mb-4">
        <a href="/admin/users" class="p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:text-black dark:hover:text-white hover:border-black dark:hover:border-white transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Create New User</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Add a new member to your platform.</p>
        </div>
    </div>
</div>

<div class="w-full">
    <form action="/admin/users" method="POST">
        @csrf

        <div class="space-y-8">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
                <div class="p-8 border-b border-slate-50 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-white">User Information</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Fill in the details for the new user account.</p>
                </div>

                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">Full Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500"
                                placeholder="John Doe">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">Email Address</label>
                            <input type="email" name="email" value="{{ old('email') }}" required
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500"
                                placeholder="john@example.com">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">Password</label>
                            <input type="password" name="password" required
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500"
                                placeholder="Minimum 8 characters">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">Confirm Password</label>
                            <input type="password" name="password_confirmation" required
                                class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500"
                                placeholder="Repeat the password">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4 pb-12">
                <a href="/admin/users" class="px-8 py-3.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all active:scale-95">
                    Cancel
                </a>
                <button type="submit" class="px-8 py-3.5 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold shadow-xl shadow-black/10 dark:shadow-white/10 hover:bg-slate-800 dark:hover:bg-slate-200 transition-all active:scale-95">
                    Create User
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
