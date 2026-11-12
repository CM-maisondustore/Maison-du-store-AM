{{-- Éléments d'une rubrique à la corbeille. Attend : $type, $items (paginé ou collection), $showType (facultatif). --}}
@php($trashLabel=\App\Services\Trash::TYPES[$type][1])
<section class="panel flush" aria-label="Corbeille : {{ $trashLabel }}">
@if(empty($compact))<p class="panel-note">Restaurables pendant {{ \App\Services\Trash::DAYS }} jours, puis supprimés définitivement.</p>@endif
<div class="table-wrap">
<table class="cards">
<thead><tr><th scope="col">{{ $trashLabel }}</th><th scope="col">Mis à la corbeille</th><th scope="col">Suppression définitive</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
<tbody>
@forelse($items as $item)
@php($purge=$item->purgeDate())
<tr>
<td class="primary">{{ \App\Services\Trash::title($item) }}@if($item->archived_at)<small>Archivé avant suppression</small>@endif</td>
<td data-label="Mis à la corbeille"><span class="mono">{{ $item->deleted_at->format('d/m/Y H:i') }}</span>@if($item->deletedBy)<small>par {{ $item->deletedBy->name }}</small>@endif</td>
<td data-label="Suppression définitive"><span class="mono">{{ $purge->format('d/m/Y') }}</span><small>{{ $purge->isPast() ? 'à la prochaine purge' : 'dans '.max(1,(int)ceil(now()->diffInHours($purge)/24)).' jour(s)' }}</small></td>
<td class="trash-actions">
<form method="post" action="{{ route('staff.trash.restore',[$type,$item->id]) }}">@csrf<button class="secondary">Restaurer</button></form>
<form method="post" action="{{ route('staff.trash.force',[$type,$item->id]) }}" data-confirm="Supprimer définitivement « {{ \App\Services\Trash::title($item) }} » ? Cette action est irréversible.">@csrf @method('DELETE')<button class="danger">Supprimer définitivement</button></form>
</td>
</tr>
@empty<tr><td colspan="4" class="empty">Aucun élément à la corbeille.</td></tr>
@endforelse
</tbody>
</table>
</div>
@if($items instanceof \Illuminate\Contracts\Pagination\Paginator)@include('staff.pagination',['paginator'=>$items])@endif
</section>
