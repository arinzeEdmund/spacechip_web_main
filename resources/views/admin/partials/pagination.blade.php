@if ($paginator->hasPages())
    <nav class="mt-5 flex items-center justify-between gap-4 text-[13px]" aria-label="Pagination">
        <span class="text-muted tabular">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }}</span>
        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-9 items-center rounded-xl px-3 text-faint ring-1 ring-ink/[.06]">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex h-9 items-center rounded-xl bg-surface px-3 ring-1 ring-ink/[.08] hover:bg-surface-2">Previous</a>
            @endif
            <span class="px-2 text-muted tabular">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex h-9 items-center rounded-xl bg-surface px-3 ring-1 ring-ink/[.08] hover:bg-surface-2">Next</a>
            @else
                <span class="inline-flex h-9 items-center rounded-xl px-3 text-faint ring-1 ring-ink/[.06]">Next</span>
            @endif
        </div>
    </nav>
@endif
