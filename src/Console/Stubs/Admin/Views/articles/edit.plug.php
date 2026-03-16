@extends('admin::layouts.admin')

@section('title', 'Edit Article')

@section('content')
<div class="mb-10 w-full">
    <div class="flex items-center gap-3 mb-2">
        <a href="/admin/articles" class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Edit Article</h1>
    </div>
    <p class="text-slate-500 dark:text-slate-400 font-medium">Update your article's content and settings.</p>
</div>

<form action="/admin/articles/{{ $article->id }}" method="POST">
    @csrf
    
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main Content (Left Column) -->
        <div class="flex-1 space-y-6">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-8">
                <div class="mb-6">
                    <label for="title" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Title</label>
                    <input type="text" id="title" name="title" value="{{ $article->title }}" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500">
                </div>

                <div class="mb-6">
                    <label for="slug" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ $article->slug }}" class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium placeholder-slate-400 dark:placeholder-slate-500">
                </div>

                <div class="mb-6">
                    <label for="content" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Content</label>
                    <textarea id="content" name="content" rows="15" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium custom-scrollbar placeholder-slate-400 dark:placeholder-slate-500">{{ $article->content }}</textarea>
                </div>
            </div>
        </div>

        <!-- Sidebar Options (Right Column) -->
        <div class="lg:w-[350px] space-y-6">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-8">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6">Article Settings</h3>
                
                <div class="mb-6">
                    <label for="status" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Publishing Status</label>
                    <div class="relative">
                        <select id="status" name="status" class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium appearance-none cursor-pointer bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5%22%20stroke%3D%22%2394a3b8%22%20stroke-width%3D%222%22%20fill%3D%22none%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:right_1.25rem_center]">
                            <option value="draft" {{ $article->status === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="published" {{ $article->status === 'published' ? 'selected' : '' }}>Published</option>
                            <option value="archived" {{ $article->status === 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="summary" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Short Summary</label>
                    <textarea id="summary" name="summary" rows="4" class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-4 focus:ring-black/5 dark:focus:ring-white/5 focus:border-black dark:focus:border-white transition-all font-medium custom-scrollbar placeholder-slate-400 dark:placeholder-slate-500">{{ $article->summary }}</textarea>
                </div>

                <div class="pt-6 border-t border-slate-50 dark:border-slate-800 space-y-4">
                    <button type="submit" class="w-full px-8 py-4 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold shadow-xl shadow-black/10 dark:shadow-white/10 hover:bg-slate-800 dark:hover:bg-slate-200 transition-all active:scale-95 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Save Changes
                    </button>
                    <a href="/admin/articles" class="w-full px-8 py-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-100 dark:hover:bg-slate-750 transition-all active:scale-95 flex items-center justify-center">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
