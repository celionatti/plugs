@extends('admin::layouts.admin')

@section('title', 'Users Management')

@section('content')
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Users Management</h1>
        <p class="text-slate-500 mt-1 font-medium">Create, edit and organize your team members.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="/admin/users/create" class="px-6 py-3 rounded-2xl bg-black text-white font-bold text-sm shadow-xl shadow-black/10 hover:shadow-black/20 hover:-translate-y-0.5 transition-all flex items-center gap-2 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New User
        </a>
    </div>
</div>

<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-8 border-b border-slate-50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-50/30">
        <div class="flex items-center gap-4">
            <h2 class="text-lg font-bold text-slate-800">Team Members</h2>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-50">
                    <th class="px-8 py-5">User</th>
                    <th class="px-8 py-5">Email</th>
                    <th class="px-8 py-5">Joined</th>
                    <th class="px-8 py-5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @if(!empty($users) && (is_array($users) ? count($users) > 0 : $users->count() > 0))
                    @foreach($users as $user)
                    <tr class="group hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-black flex items-center justify-center text-white font-bold text-sm shadow-md shadow-black/5">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800 text-sm">{{ $user->name }}</div>
                                    <div class="text-xs text-slate-400 font-medium">ID: {{ $user->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="text-sm text-slate-600 font-medium">{{ $user->email }}</span>
                        </td>
                        <td class="px-8 py-5">
                            <span class="text-sm text-slate-500 font-medium">{{ $user->created_at ?? 'N/A' }}</span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="/admin/users/{{ $user->id }}/edit" class="p-2 rounded-xl text-slate-400 hover:text-black hover:bg-slate-100 transition-all" title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                <form action="/admin/users/{{ $user->id }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all" title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                @else
                <tr>
                    <td colspan="4" class="px-8 py-24 text-center">
                        <div class="flex flex-col items-center max-w-xs mx-auto">
                            <div class="w-20 h-20 rounded-3xl bg-slate-50 flex items-center justify-center text-slate-200 mb-6">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800">No Users Found</h3>
                            <p class="text-sm text-slate-500 mt-2 font-medium">Your team is currently empty. Add your first user to get started.</p>
                            <a href="/admin/users/create" class="mt-8 px-6 py-3 rounded-2xl bg-black text-white font-bold text-sm hover:bg-slate-800 transition-all active:scale-95 shadow-xl shadow-black/10">
                                Add First Member
                            </a>
                        </div>
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
