@extends('staff.layout')
@section('title','Aperçu du message')
@section('content')<p>Données fictives. Aucun envoi et aucun enregistrement n’ont été effectués.</p>
<section class="panel"><h2>{{ $subject }}</h2><div class="prose">{{ $body }}</div></section>
@endsection
