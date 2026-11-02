@extends('staff.layout')
@section('title','Rendez-vous du '.\App\Services\StaffInterface::date($appointment->date))
@section('back',route('staff.appointments.index'))
@section('crumbs')<a href="{{ route('staff.appointments.index') }}">Rendez-vous</a>@endsection
@section('page-actions')@include('staff.status',['status'=>$appointment->status])@endsection
@section('content')
@php($admin=auth()->user()->isAdmin())
<div class="with-aside">
<section class="panel">
<h2>Demande du client</h2>
<dl class="detail-list">
@foreach($appointment->details as $key=>$value)<dt>{{ config('workspace.labels.'.$key, ucfirst($key)) }}</dt>
<dd>@if($key==='phone' && $value)<a href="tel:{{ preg_replace('/[^0-9+]/','',$value) }}">{{ $value }}</a>@else{{ is_array($value) ? implode(', ',$value) : $value }}@endif</dd>
@endforeach</dl>
</section>
<aside>
<section class="panel">
<h2>Organisation</h2>
@if($admin)
@if(in_array($appointment->status,['pending','confirmed']))
<div class="actions">
@foreach($appointment->status==='pending' ? ['confirmed'=>['Confirmer le rendez-vous','']] : [] as $state=>$action)
<form method="post" action="{{ route('staff.appointments.update',$appointment) }}">@csrf @method('PUT')<input type="hidden" name="date" value="{{ $appointment->date }}"><input type="hidden" name="time" value="{{ $appointment->time }}"><input type="hidden" name="agent_id" value="{{ $appointment->agent_id }}"><input type="hidden" name="internal_notes" value="{{ $appointment->internal_notes }}"><button name="status" value="{{ $state }}">{{ $action[0] }}</button></form>
@endforeach
<form method="post" action="{{ route('staff.appointments.update',$appointment) }}" data-confirm="Annuler ce rendez-vous ? Le créneau sera libéré.">@csrf @method('PUT')<input type="hidden" name="date" value="{{ $appointment->date }}"><input type="hidden" name="time" value="{{ $appointment->time }}"><input type="hidden" name="agent_id" value="{{ $appointment->agent_id }}"><input type="hidden" name="internal_notes" value="{{ $appointment->internal_notes }}"><button class="danger" name="status" value="cancelled">Annuler</button></form>
</div>
<p class="muted">Annuler libère le créneau.</p>
@endif
<form method="post" action="{{ route('staff.appointments.update',$appointment) }}">
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
<button class="secondary">Enregistrer</button>
</div>
</form>
<p><a class="link" href="{{ route('staff.messages.create',['appointment_id'=>$appointment->id]) }}">Préparer la communication au client</a></p>
<p><a class="link" href="{{ route('staff.interventions.index',['contact_id'=>$appointment->contact_id,'appointment_id'=>$appointment->id]) }}#planifier">Planifier une prestation liée</a></p>
@else<p class="mono">{{ $appointment->time }} · {{ $appointment->agent?->name }}</p>
<p class="prose">{{ $appointment->internal_notes }}</p>
@endif</section>
</aside>
</div>
@endsection
