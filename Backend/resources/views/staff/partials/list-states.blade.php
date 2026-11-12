{{-- Onglets En cours / Archivés / Corbeille d'une liste (admin). Attend : $type (clé de Trash::TYPES), $state. --}}
@if(auth()->user()->isAdmin())
@php($stateCounts=\App\Services\Trash::counts($type))
@php($stateRoute='staff.'.\App\Services\Trash::TYPES[$type][2])
@php($stateKeep=request()->only('q'))
<nav class="tabs page-tabs" aria-label="En cours, archivés ou à la corbeille">
<a class="tab" href="{{ route($stateRoute,$stateKeep) }}" @if(!$state) aria-current="page" @endif>En cours</a>
<a class="tab" href="{{ route($stateRoute,[...$stateKeep,'etat'=>'archives']) }}" @if($state==='archives') aria-current="page" @endif>Archivés<b>{{ $stateCounts['archives'] }}</b></a>
<a class="tab" href="{{ route($stateRoute,[...$stateKeep,'etat'=>'corbeille']) }}" @if($state==='corbeille') aria-current="page" @endif>@include('staff.partials.icon',['n'=>'trash','s'=>16])Corbeille<b>{{ $stateCounts['corbeille'] }}</b></a>
</nav>
@error('trash')<p class="notice" role="alert">{{ $message }}</p>@enderror
@endif
