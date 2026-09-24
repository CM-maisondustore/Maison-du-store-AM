
@extends('staff.layout')
@section('title','Factures')
@section('content')<p class="muted">Préparez une facture depuis un devis accepté. Les factures émises conservent les coordonnées et montants d’origine.</p>
<div class="panel table-wrap">
<table>
<thead>
<tr>
<th>Numéro</th>
<th>Client</th>
<th>Échéance</th>
<th>État</th>
</tr>
</thead>
<tbody>
@forelse($invoices as $invoice)<tr>
<td>
<a href="{{ route('staff.invoices.show',$invoice) }}">{{ $invoice->number ?? 'Brouillon #'.$invoice->id }}</a>
</td>
<td>{{ $invoice->contact->name }}</td>
<td>{{ $invoice->due_date->format('d/m/Y') }}</td>
<td>
@include('staff.status',['status'=>$invoice->status])</td>
</tr>
@empty<tr>
<td colspan="4" class="empty">Aucune facture.</td>
</tr>
@endforelse</tbody>
</table>
@include('staff.pagination',['paginator'=>$invoices])</div>
@endsection
