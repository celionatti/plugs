@extends('admin::layouts.admin')

@section('title', 'Articles Management')

@section('content')
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10 w-full">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">Articles Management</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Create, edit and organize your publication's articles.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="/admin/articles/create" class="px-6 py-3 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold text-sm shadow-xl shadow-black/10 hover:shadow-black/20 dark:hover:shadow-white/20 hover:-translate-y-0.5 transition-all flex items-center gap-2 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Article
        </a>
    </div>
</div>

<div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden w-full">
    <div class="p-8 border-b border-slate-50 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-50/30 dark:bg-slate-800/30">
        <div class="flex items-center gap-4">
            <h2 class="text-lg font-bold text-slate-800 dark:text-white">All Articles</h2>
        </div>
    </div>

    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left border-collapse min-w-max">
            <thead>
                <tr class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest border-b border-slate-50 dark:border-slate-800">
                    <th class="px-8 py-5">Title</th>
                    <th class="px-8 py-5">Status</th>
                    <th class="px-8 py-5">Published At</th>
                    <th class="px-8 py-5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                @if(!empty($articles) && (is_array($articles) ? count($articles) > 0 : $articles->count() > 0))
                    @foreach($articles as $article)
                    <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="font-bold text-slate-800 dark:text-slate-100 text-sm">{{ $article->title }}</div>
                            <div class="text-xs text-slate-400 dark:text-slate-500 font-medium">Slug: {{ $article->slug }}</div>
                        </td>
                        <td class="px-8 py-5">
                            @if($article->status === 'published')
                                <span class="px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-xs font-bold">Published</span>
                            @elseif($article->status === 'archived')
                                <span class="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-400 text-xs font-bold">Archived</span>
                            @else
                                <span class="px-3 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-xs font-bold">Draft</span>
                            @endif
                        </td>
                        <td class="px-8 py-5">
                            <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">{{ $article->published_at ?? 'N/A' }}</span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="/admin/articles/{{ $article->id }}/edit" class="p-2 rounded-xl text-slate-400 dark:text-slate-500 hover:text-black dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition-all" title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                <form action="/admin/articles/{{ $article->id }}/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this article?');" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="p-2 rounded-xl text-slate-400 dark:text-slate-500 hover:text-rose-600 dark:hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all" title="Delete">
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
                            <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-200 dark:text-slate-600 mb-6">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM14 4v4h4" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 dark:text-white">No Articles Found</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 font-medium">Your publication is empty. Write your first article today.</p>
                            <a href="/admin/articles/create" class="mt-8 px-6 py-3 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold text-sm hover:bg-slate-800 dark:hover:bg-slate-200 transition-all active:scale-95 shadow-xl shadow-black/10 dark:shadow-white/10">
                                Create Article
                            </a>
                        </div>
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
    
    @if(!empty($articles) && method_exists($articles, 'links'))
    <div class="p-6 border-t border-slate-50 dark:border-slate-800">
        {!! $articles->links() !!}
    </div>
    @endif
</div>
@endsection
