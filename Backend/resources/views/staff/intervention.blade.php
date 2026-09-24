
@extends('staff.layout')
@section('title','Prestation #'.$job->id)
@section('content')<section class="panel">
<h2>{{ $job->contact->name }}</h2>
<p>{{ $job->contact->phone }} · {{ $job->contact->email }}</p>
<p class="prose">{{ $job->address }}</p>
<p>{{ $job->scheduled_at->format('d/m/Y à H:i') }} · {{ $job->duration_minutes }} minutes · {{ $job->agent->name }}</p>
<p class="prose">{{ $job->instructions }}</p>
@include('staff.status',['status'=>$job->status])</section>
<form class="panel" method="post" action="{{ route('staff.interventions.update',$job) }}">
@csrf
@method('PUT')
@if(auth()->user()->isAdmin())
@include('staff.planning-fields')
@endif<label>Avancement<select name="status">
@foreach(['planned'=>'Planifié','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'] as $value=>$label)
@if($value!=='cancelled' || auth()->user()->isAdmin())<option value="{{ $value }}" @selected(old('status',$job->status)===$value)>{{ $label }}</option>
@endif
@endforeach</select>
</label>
<label>Compte rendu de l’intervention<textarea name="report" rows="7">{{ old('report',$job->report) }}</textarea>
</label>
<div class="actions">
<button>Enregistrer le suivi</button>
</div>
</form>
<section class="panel">
<h2>Devis sur place</h2>
@if($job->quote_id)<a href="{{ route('staff.quotes.show',$job->quote_id) }}">Ouvrir le devis lié</a>
@else<form method="post" action="{{ route('staff.interventions.quote',$job) }}">
@csrf<label>Type de projet<select name="type">
<option value="store">Store</option>
<option value="rideau">Rideau</option>
</select>
</label>
<div class="actions">
<button>Créer un devis pour ce client</button>
</div>
</form>
@endif</section>
@endsection
