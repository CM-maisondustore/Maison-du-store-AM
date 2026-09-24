
@if($paginator->hasPages())<nav class="pagination" aria-label="Pagination">
<span>
@if($paginator->previousPageUrl())<a href="{{ $paginator->previousPageUrl() }}">← Précédent</a>
@endif</span>
<span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
<span>
@if($paginator->nextPageUrl())<a href="{{ $paginator->nextPageUrl() }}">Suivant →</a>
@endif</span>
</nav>
@endif
