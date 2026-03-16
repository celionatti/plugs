@extends('admin::layouts.admin')

@section('title', 'Create Article')

@section('content')
<div class="mb-10 w-full">
    <div class="flex items-center gap-3 mb-2">
        <a href="/admin/articles" class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Create Article</h1>
    </div>
    <p class="text-slate-500 dark:text-slate-400 font-medium">Draft a new post for your publication.</p>
</div>

<div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden w-full max-w-4xl">
    <div class="p-8">
        <form action="/admin/articles" method="POST">
            @csrf
            
            <div class="mb-6">
                <label for="title" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Title</label>
                <input type="text" id="title" name="title" required class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all placeholder-slate-400 dark:placeholder-slate-500">
            </div>

            <div class="mb-6">
                <label for="slug" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Slug (Optional)</label>
                <input type="text" id="slug" name="slug" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all placeholder-slate-400 dark:placeholder-slate-500">
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Leave empty to auto-generate from title.</p>
            </div>

            <div class="mb-6">
                <label for="content" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Content</label>
                <textarea id="content" name="content" rows="10" required class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all custom-scrollbar placeholder-slate-400 dark:placeholder-slate-500"></textarea>
            </div>

            <div class="mb-6">
                <label for="summary" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Summary</label>
                <textarea id="summary" name="summary" rows="3" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all custom-scrollbar placeholder-slate-400 dark:placeholder-slate-500"></textarea>
            </div>

            <div class="mb-8">
                <label for="status" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Status</label>
                <select id="status" name="status" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all appearance-none cursor-pointer bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5%22%20stroke%3D%22%2394a3b8%22%20stroke-width%3D%222%22%20fill%3D%22none%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:right_1rem_center]">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-4 pt-6 border-t border-slate-100 dark:border-slate-800">
                <a href="/admin/articles" class="px-6 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-sm hover:bg-slate-200 dark:hover:bg-slate-700 transition-all active:scale-95">Cancel</a>
                <button type="submit" class="px-6 py-3 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold text-sm shadow-xl shadow-black/10 dark:shadow-white/10 hover:shadow-black/20 dark:hover:shadow-white/20 hover:-translate-y-0.5 transition-all active:scale-95">Create Article</button>
            </div>
        </form>
    </div>
</div>
@endsection
