
@extends('staff.layout')
@section('title','Rendez-vous du '.$appointment->date)
@section('content')<div class="grid">
<section class="panel">
<h2>Demande du client</h2>
<dl class="detail-list">
@foreach($appointment->details as $key=>$value)<dt>{{ config('workspace.labels.'.$key, ucfirst($key)) }}</dt>
<dd>{{ $value }}</dd>
@endforeach</dl>
</section>
<section class="panel">
<h2>Organisation</h2>
@include('staff.status',['status'=>$appointment->status])
@if(auth()->user()->isAdmin())<form method="post" action="{{ route('staff.appointments.update',$appointment) }}">
@csrf
@method('PUT')<label>Date<input type="date" name="date" required value="{{ old('date',$appointment->date) }}">
</label>
<label>Heure (Abidjan)<select name="time">
@foreach(config('appointments.slots') as $slot)<option @selected(old('time',$appointment->time)===$slot)>{{ $slot }}</option>
@endforeach</select>
</label>
<label>État<select name="status">
@foreach(['pending'=>'À confirmer','confirmed'=>'Confirmé','completed'=>'Terminé','cancelled'=>'Annulé'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$appointment->status)===$value)>{{ $label }}</option>
@endforeach</select>
</label>
<label>Agent<select name="agent_id">
<option value="">Non affecté</option>
@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('agent_id',$appointment->agent_id)==$agent->id)>{{ $agent->name }}</option>
@endforeach</select>
</label>
<label>Notes internes<textarea name="internal_notes">{{ old('internal_notes',$appointment->internal_notes) }}</textarea>
</label>
<div class="actions">
<button>Enregistrer</button>
</div>
</form>
<a href="{{ route('staff.messages.create',['appointment_id'=>$appointment->id]) }}">Préparer la communication au client</a>
<p>
<a href="{{ route('staff.interventions.index',['contact_id'=>$appointment->contact_id,'appointment_id'=>$appointment->id]) }}">Planifier une prestation liée</a>
</p>
@else<p>{{ $appointment->time }} · {{ $appointment->agent?->name }}</p>
<p class="prose">{{ $appointment->internal_notes }}</p>
@endif</section>
</div>
@endsection
