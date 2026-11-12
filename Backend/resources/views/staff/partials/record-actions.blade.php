{{-- Archiver / mettre à la corbeille depuis une fiche (admin). Attend : $type, $record. --}}
@if(auth()->user()->isAdmin())
@php($blocker=\App\Services\Trash::blocker($record))
<section class="panel record-actions" aria-labelledby="h-record-actions-{{ $type }}">
<h2 id="h-record-actions-{{ $type }}">Archives et corbeille</h2>
@if($record->archived_at)<p class="muted">Archivé le {{ $record->archived_at->format('d/m/Y') }} : hors de la liste courante, visible dans l’onglet « Archivés ».</p>@endif
<div class="actions">
@if($record->archived_at)
<form method="post" action="{{ route('staff.trash.unarchive',[$type,$record->id]) }}">@csrf<button class="secondary">Désarchiver</button></form>
@else
<form method="post" action="{{ route('staff.trash.archive',[$type,$record->id]) }}">@csrf<button class="secondary">Archiver</button></form>
@endif
@if($blocker)
<button type="button" class="danger" disabled aria-describedby="trash-why-{{ $type }}">Mettre à la corbeille</button>
@else
<form method="post" action="{{ route('staff.trash.destroy',[$type,$record->id]) }}" data-confirm="Mettre à la corbeille ? L’élément reste restaurable pendant {{ \App\Services\Trash::DAYS }} jours, puis il est supprimé définitivement.">@csrf @method('DELETE')<button class="danger">Mettre à la corbeille</button></form>
@endif
</div>
@if($blocker)<p class="hint" id="trash-why-{{ $type }}">{{ $blocker }}</p>@endif
@error('trash')<p class="field-error" role="alert">{{ $message }}</p>@enderror
</section>
@endif
