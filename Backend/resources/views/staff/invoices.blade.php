@extends('staff.layout')
@section('title','Factures')
@section('subtitle','Préparez une facture depuis un devis accepté. Les factures émises conservent les coordonnées et montants d’origine.')
@section('content')
@include('staff.partials.list-states',['type'=>'factures','state'=>$state])
@if($state==='corbeille')
@include('staff.partials.trash-table',['type'=>'factures','items'=>$invoices])
@else
<nav class="chips" aria-label="Filtrer par état">
<a class="chip" href="{{ route('staff.invoices.index') }}" @if(!request('status')) aria-current="true" @endif>Toutes<b>{{ $counts->sum() }}</b></a>
@foreach(['draft'=>'Brouillon','issued'=>'Émise','paid'=>'Payée'] as $value=>$label)<a class="chip" href="{{ route('staff.invoices.index',['status'=>$value]) }}" @if(request('status')===$value) aria-current="true" @endif>{{ $label }}<b>{{ $counts[$value] ?? 0 }}</b></a>@endforeach
</nav>
<section class="panel flush table-wrap">
<table class="cards">
<thead><tr><th scope="col">Numéro</th><th scope="col">Client</th><th scope="col" class="num">Total</th><th scope="col">Échéance</th><th scope="col">État</th></tr></thead>
<tbody>
@forelse($invoices as $invoice)<tr>
<td class="primary"><a class="row-link mono" href="{{ route('staff.invoices.show',$invoice) }}">{{ $invoice->number ?? 'Brouillon #'.$invoice->id }}</a></td>
<td data-label="Client">{{ $invoice->contact->name }}</td>
<td class="num amount" data-label="Total">{{ \App\Services\StaffInterface::money(\App\Services\Quotes\QuoteFinancials::forSnapshot($invoice->snapshot)['total'] ?? 0) }}</td>
<td class="mono" data-label="Échéance">{{ $invoice->due_date->format('d/m/Y') }}</td>
<td class="state">@include('staff.status',['status'=>$invoice->status])</td>
</tr>
@empty<tr><td colspan="5" class="empty">Aucune facture.</td></tr>
@endforelse</tbody>
</table>
@include('staff.pagination',['paginator'=>$invoices])
</section>
@endif
@endsection
