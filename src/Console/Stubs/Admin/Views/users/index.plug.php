@extends('admin::layouts.admin')

@section('title', 'Users Management')

@section('content')
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10 w-full">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Users Management</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Manage and organize your platform's members.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="/admin/users/create" class="px-6 py-3 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold text-sm shadow-xl shadow-black/10 hover:shadow-black/20 dark:hover:shadow-white/20 hover:-translate-y-0.5 transition-all flex items-center gap-2 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create User
        </a>
    </div>
</div>

<div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden w-full">
    <div class="p-8 border-b border-slate-50 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-50/30 dark:bg-slate-800/30">
        <div class="flex items-center gap-4">
            <h2 class="text-lg font-bold text-slate-800 dark:text-white">Directory</h2>
        </div>
    </div>

    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left border-collapse min-w-max">
            <thead>
                <tr class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest border-b border-slate-50 dark:border-slate-800">
                    <th class="px-8 py-5">Member</th>
                    <th class="px-8 py-5">Role</th>
                    <th class="px-8 py-5">Joined At</th>
                    <th class="px-8 py-5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                @if(!empty($users) && (is_array($users) ? count($users) > 0 : $users->count() > 0))
                    @foreach($users as $user)
                    <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-2xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center font-bold text-sm shadow-lg shadow-black/10 dark:shadow-white/10 group-hover:scale-110 transition-transform">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                                <div class="flex flex-col">
                                    <div class="font-bold text-slate-800 dark:text-slate-100 text-sm italic brand-font">{{ $user->name }}</div>
                                    <div class="text-xs text-slate-400 dark:text-slate-500 font-medium">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 rounded-full bg-secondary/10 dark:bg-secondary/20 text-secondary text-xs font-bold">{{ $user->role ?? 'Member' }}</span>
                        </td>
                        <td class="px-8 py-5 text-sm text-slate-500 dark:text-slate-400 font-medium">
                            {{ date('M d, Y', strtotime($user->created_at)) }}
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="/admin/users/{{ $user->id }}" class="p-2 rounded-xl text-slate-400 dark:text-slate-500 hover:text-black dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                <a href="/admin/users/{{ $user->id }}/edit" class="p-2 rounded-xl text-slate-400 dark:text-slate-500 hover:text-black dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                @else
                <tr><td colspan="4" class="px-8 py-10 text-center text-slate-400 italic">No users found.</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    @if(!empty($users) && method_exists($users, 'links'))
    <div class="p-6 border-t border-slate-50 dark:border-slate-800">
        {!! $users->links() !!}
    </div>
    @endif
</div>
@endsection
