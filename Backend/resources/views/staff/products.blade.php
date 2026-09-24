
@extends('staff.layout')
@section('title', 'Catalogue')
@section('content')<div class="actions">
<a class="button" href="{{ route('staff.products.create') }}">Ajouter un produit</a>
</div>
<div class="panel table-wrap">
<table>
<thead>
<tr>
<th>Référence</th>
<th>Produit</th>
<th>Disponibilité</th>
<th>Publication</th>
</tr>
</thead>
<tbody>
@foreach($products as $product)<tr>
<td>{{ $product->reference }}</td>
<td>
<a href="{{ route('staff.products.edit',$product) }}">{{ $product->details['name'] }}</a>
<small>{{ $product->details['category'] }}</small>
</td>
<td>{{ $product->details['available'] ? 'Disponible' : 'Indisponible' }}</td>
<td>{{ $product->published ? 'Visible sur le site' : 'Masqué' }}</td>
</tr>
@endforeach</tbody>
</table>
</div>
@endsection
