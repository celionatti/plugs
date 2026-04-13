@extends('admin::layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="mb-10">
    <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">System Overview</h1>
    <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Welcome back, Administrator. Here's what's happening today.</p>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
    <!-- Users Stat -->
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex items-center justify-between group hover:shadow-xl hover:shadow-black dark:hover:shadow-white/5 transition-all">
        <div>
            <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Total Users</span>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white mt-1">{{ $stats['users_count'] }}</div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center transition-transform group-hover:scale-110 shadow-lg shadow-slate-300 dark:shadow-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        </div>
    </div>

    <!-- Articles Stat -->
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex items-center justify-between group hover:shadow-xl hover:shadow-black dark:hover:shadow-white/5 transition-all">
        <div>
            <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Articles</span>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white mt-1">{{ $stats['articles_count'] }}</div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-secondary-100 dark:bg-secondary-200 text-secondary flex items-center justify-center transition-transform group-hover:scale-110">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM14 4v4h4" />
            </svg>
        </div>
    </div>

    <!-- Active Plugs Stat -->
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex items-center justify-between group hover:shadow-xl hover:shadow-black dark:hover:shadow-white/5 transition-all">
        <div>
            <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Active Plugs</span>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white mt-1">{{ $stats['plugs_count'] }}</div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-slate-50 dark:bg-slate-800 text-slate-400 flex items-center justify-center transition-transform group-hover:scale-110">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </div>
    </div>

    <!-- Security Tags Stat -->
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex items-center justify-between group hover:shadow-xl hover:shadow-black dark:hover:shadow-white/5 transition-all">
        <div>
            <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Security Tags</span>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white mt-1">{{ $stats['security_tags_count'] }}</div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-black dark:bg-white text-white dark:text-black flex items-center justify-center transition-transform group-hover:scale-110">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Recent Users Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="p-6 border-b border-slate-50 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800 dark:text-white">Recently Joined</h2>
            <a href="/admin/users" data-spa="true" class="text-xs font-bold text-black dark:text-white border-b border-slate-300 dark:border-white hover:border-black dark:hover:border-white transition-all pb-0.5">View All</a>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest bg-slate-50 dark:bg-slate-800">
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                    @forelse($stats['recent_users'] as $user)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-black dark:bg-white flex items-center justify-center text-white dark:text-black text-[10px] font-bold">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ $user->name }}</span>
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ $user->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500 font-medium">
                            {{ date('M d, Y', strtotime($user->created_at)) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500 text-sm italic">No users found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-8 flex flex-col justify-center">
        <h2 class="text-xl font-extrabold text-slate-800 dark:text-white mb-6">Quick Actions</h2>
        <div class="grid grid-cols-2 gap-4">
            <a href="/admin/users/create" data-spa="true" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-800 text-left hover:border-slate-300 dark:hover:border-white/20 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all group">
                <div class="w-10 h-10 rounded-xl bg-black dark:bg-white text-white dark:text-black flex items-center justify-center mb-3 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
                <span class="block text-sm font-bold text-slate-700 dark:text-slate-300">Add User</span>
                <span class="text-[10px] text-slate-400 dark:text-slate-500">Invite new team member</span>
            </a>
            <a href="/admin/articles/create" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-800 text-left hover:border-secondary-200 dark:hover:border-secondary-200 hover:bg-secondary dark:hover:bg-secondary-100 transition-all group">
                <div class="w-10 h-10 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-secondary flex items-center justify-center mb-3 transition-all group-hover:bg-secondary group-hover:text-white shadow-sm group-hover:shadow-secondary-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </div>
                <span class="block text-sm font-bold text-slate-700 dark:text-slate-300">New Article</span>
                <span class="text-[10px] text-slate-400 dark:text-slate-500">Write a fresh post</span>
            </a>
        </div>
    </div>
</div>

<div class="mt-8">
    @lazy('admin::system-info')
</div>
@endsection
