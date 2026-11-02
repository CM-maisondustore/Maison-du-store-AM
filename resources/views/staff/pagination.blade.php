@if($paginator->hasPages())<nav class="pagination" aria-label="Pagination">
<span>@if($paginator->previousPageUrl())<a class="page-step" href="{{ $paginator->previousPageUrl() }}">‹ Précédent</a>@endif</span>
<span class="mono page-now" aria-current="page">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
<span>@if($paginator->nextPageUrl())<a class="page-step" href="{{ $paginator->nextPageUrl() }}">Suivant ›</a>@endif</span>
</nav>
@endif
