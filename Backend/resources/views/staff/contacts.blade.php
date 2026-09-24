
@extends('staff.layout')
@section('title', 'Clients & contacts')
@section('content')<div class="panel">
<form method="get" class="actions">
<input name="q" aria-label="Rechercher" placeholder="Nom, e-mail ou téléphone" value="{{ request('q') }}" style="max-width:420px">
<button>Rechercher</button>
</form>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Contact</th>
<th>Coordonnées</th>
<th>Catégorie</th>
<th>État</th>
</tr>
</thead>
<tbody>
@forelse($contacts as $contact)<tr>
<td>
<a href="{{ route('staff.contacts.show',$contact) }}">{{ $contact->name }}</a>
</td>
<td>{{ $contact->email }}<small>{{ $contact->phone }}</small>
</td>
<td>{{ ucfirst($contact->kind) }}</td>
<td>{{ $contact->archived_at ? 'Archivé' : 'Actif' }}</td>
</tr>
@empty<tr>
<td colspan="4" class="empty">Aucun contact.</td>
</tr>
@endforelse</tbody>
</table>
</div>
@include('staff.pagination',['paginator'=>$contacts])</div>
<details class="panel">
<summary>Créer un contact</summary>
<form method="post" action="{{ route('staff.contacts.store') }}">
@csrf
@include('staff.contact-fields',['contact'=>new \App\Models\Contact()])<div class="actions">
<button>Créer le dossier</button>
</div>
</form>
</details>
@endsection
