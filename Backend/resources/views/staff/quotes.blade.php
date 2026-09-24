
@extends('staff.layout')
@section('title','Devis')
@section('content')<div class="panel">
<form class="actions">
<label>État<select name="status">
<option value="">Tous les devis</option>
@foreach(['pending'=>'À traiter','draft'=>'Brouillon','approved'=>'Validé','accepted'=>'Accepté','rejected'=>'Refusé','cancelled'=>'Annulé'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
@endforeach</select>
</label>
<button>Filtrer</button>
</form>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Client</th>
<th>Demande</th>
<th>Agent</th>
<th>État</th>
</tr>
</thead>
<tbody>
@forelse($quotes as $quote)<tr>
<td>
<a href="{{ route('staff.quotes.show',$quote) }}">{{ $quote->contact['firstName'] ?? '' }} {{ $quote->contact['lastName'] ?? '' }}</a>
<small>{{ $quote->contact['email'] ?? '' }}</small>
</td>
<td>{{ ucfirst($quote->type) }} · {{ $quote->source === 'site' ? 'Site' : 'Sur place' }}<small>{{ $quote->created_at->format('d/m/Y') }} · {{ substr($quote->reference,0,8) }}</small>
</td>
<td>{{ $quote->agent?->name ?? 'Non affecté' }}</td>
<td>
@include('staff.status',['status'=>$quote->status])</td>
</tr>
@empty<tr>
<td colspan="4" class="empty">Aucun devis à afficher.</td>
</tr>
@endforelse</tbody>
</table>
</div>
@include('staff.pagination',['paginator'=>$quotes])</div>
@endsection
