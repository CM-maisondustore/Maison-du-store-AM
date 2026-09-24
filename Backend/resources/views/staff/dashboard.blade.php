
@extends('staff.layout')
@section('title', 'Vue d’ensemble')
@section('content')<div class="stats">
@foreach($counts as $label=>$count)<div class="stat">
<strong>{{ $count }}</strong>
<span>{{ $label }}</span>
</div>
@endforeach</div>
<section class="panel">
<h2>Prestations à venir</h2>
@include('staff.jobs-table')<div class="actions">
<a href="{{ route('staff.interventions.index') }}">Toutes les prestations →</a>
</div>
</section>
@endsection
