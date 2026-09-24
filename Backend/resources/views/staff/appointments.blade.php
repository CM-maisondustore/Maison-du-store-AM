
@extends('staff.layout')
@section('title','Rendez-vous')
@section('content')<div class="panel table-wrap">
<table>
<thead>
<tr>
<th>Date / heure d’Abidjan</th>
<th>Client</th>
<th>Agent</th>
<th>État</th>
</tr>
</thead>
<tbody>
@forelse($appointments as $appointment)<tr>
<td>
<a href="{{ route('staff.appointments.show',$appointment) }}">{{ $appointment->date }} · {{ $appointment->time }}</a>
</td>
<td>{{ $appointment->details['firstName'] }} {{ $appointment->details['lastName'] }}<small>{{ $appointment->details['appointmentType'] }}</small>
</td>
<td>{{ $appointment->agent?->name ?? 'Non affecté' }}</td>
<td>
@include('staff.status',['status'=>$appointment->status])</td>
</tr>
@empty<tr>
<td colspan="4" class="empty">Aucun rendez-vous.</td>
</tr>
@endforelse</tbody>
</table>
@include('staff.pagination',['paginator'=>$appointments])</div>
@endsection
