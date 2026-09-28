@extends('staff.layout')
@section('title',$template->name.' · '.strtoupper($template->channel))
@section('content')
<div class="actions"><a href="{{ route('staff.communications.index') }}">← Documents & automatisations</a></div>
<form method="post" enctype="multipart/form-data" action="{{ route('staff.communications.update',$template) }}">
@csrf<input type="hidden" name="version" value="{{ $template->version }}">
@if($template->channel==='pdf')
@php($options=$template->options ?? [])
@php($issuer=\App\Services\CommunicationTemplates::issuer($options))
<section class="panel"><h2>Présentation du PDF</h2><div class="grid">
<label>Couleur principale<input name="accent" type="color" value="{{ old('accent',$options['accent'] ?? '#243831') }}" required></label>
<label>Logo PNG ou JPEG (512 Ko maximum)<input type="file" name="logo" accept="image/png,image/jpeg"></label>
</div>
@if(!empty($options['logo']))<img src="{{ $options['logo'] }}" alt="Logo actuel" style="max-width:180px;max-height:90px"><label class="check"><input type="checkbox" name="remove_logo" value="1">Retirer le logo</label>@endif
<label>Texte d’introduction<textarea name="body" maxlength="10000" rows="4">{{ old('body',$template->body) }}</textarea></label>
<label>Pied de page<textarea name="footer" maxlength="2000" rows="3">{{ old('footer',$options['footer'] ?? '') }}</textarea></label>
<label>Signature / mention de signature<textarea name="signature" maxlength="1000" rows="2">{{ old('signature',$options['signature'] ?? '') }}</textarea></label></section>
<section class="panel"><h2>Émetteur et conditions</h2>
@foreach(['name'=>'Nom de l’entreprise','address'=>'Adresse','registration'=>'Identifiants de l’entreprise','tax_note'=>'Mention fiscale','payment_terms'=>'Conditions de paiement'] as $key=>$label)
<label>{{ $label }}<textarea name="issuer_{{ $key }}" rows="2" @required($key==='name')>{{ old('issuer_'.$key,$issuer[$key]) }}</textarea></label>@endforeach
<p>Adresse de contact : {{ $issuer['email'] }}. Les montants et taxes proviennent du devis ou de la facture.</p></section>
@else
<section class="panel"><p>Variables disponibles : @foreach(\App\Services\CommunicationTemplates::VARIABLES as $variable)<code>&#123;&#123;{{ $variable }}&#125;&#125;</code> @endforeach</p>
<p>Les informations disponibles dépendent du document associé. Les variables sans valeur restent vides. Utilisez l’aperçu pour vérifier votre modèle.</p>
<label>Objet / titre<input name="subject" maxlength="200" value="{{ old('subject',$template->subject) }}" required></label>
<label>Message<textarea name="body" rows="15" maxlength="{{ $template->channel==='whatsapp' ? 3000 : 10000 }}" required>{{ old('body',$template->body) }}</textarea></label></section>
@endif
<div class="actions"><button>Enregistrer le modèle</button><button class="secondary" formaction="{{ route('staff.communications.preview',$template) }}" formtarget="_blank">Aperçu sans enregistrer</button></div>
</form>
@endsection
