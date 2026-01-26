@if ($paginator->hasPages())
<nav class="pagination flex items-center justify-between px-4 py-3">
    <div class="flex flex-1 justify-between sm:justify-start gap-2">
        @if ($paginator->onFirstPage())
        <span
            class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Previous</span>
        @else
        <a href="{{ $paginator->previousPageUrl() }}"
            class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
        @endif

        @if ($paginator->hasNextPage())
        <a href="{{ $paginator->nextPageUrl() }}"
            class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
        @else
        <span
            class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Next</span>
        @endif
    </div>
</nav>
@endif