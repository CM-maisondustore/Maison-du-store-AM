@extends('staff.layout')
@section('title','Mise en page des documents')
@section('content')
<link rel="stylesheet" href="{{ asset('document-layout.css') }}?v={{ filemtime(public_path('document-layout.css')) }}">
<nav class="actions" aria-label="Types de documents">@foreach(['global'=>'Tous les documents','quote'=>'Devis','invoice'=>'Factures','receipt'=>'Reçus'] as $key=>$label)<a class="button {{ $key===$template->key?'':'secondary' }}" href="{{ route('staff.layouts.edit',$key) }}">{{ $label }}</a>@endforeach</nav>
<p>Préparez un brouillon, vérifiez le PDF puis publiez le modèle. Les documents déjà émis restent inchangés.</p>
@if($draft)<p class="flash">Un brouillon est en cours. La version publiée reste utilisée pour les documents.</p>@endif
@php($issuer=\App\Services\CommunicationTemplates::issuer($options))
@php($activePresentation=\App\Services\DocumentLayout::presentation($template->key))
<div class="layout-editor">
<form id="layout-form" method="post" enctype="multipart/form-data" action="{{ route('staff.layouts.save',$template->key) }}" data-preview="{{ route('staff.layouts.preview',$template->key) }}">
@csrf<input type="hidden" name="version" value="{{ $template->version }}">
<details class="panel" open><summary>Modèle et publication</summary>
<p>Version de travail {{ $template->version }} · {{ ($activePresentation['renderer_version'] ?? 1)===2?'Modèle premium publié':'Réglages historiques conservés' }}@if(isset($activePresentation['global_template_version'])) · style commun v{{ $activePresentation['global_template_version'] }}@endif</p>
<label>Nom du modèle<input name="name" maxlength="150" required value="{{ old('name',$draft['name'] ?? $template->name) }}"></label>
@if($template->key==='global')
<input type="hidden" name="apply_all" value="0"><label class="check"><input type="checkbox" name="apply_all" value="1" checked>Appliquer le style commun aux devis, factures et reçus lors de la publication</label>
<p class="muted">Les modèles déjà rattachés suivent toujours le style commun. Cette option rattache aussi les modèles personnalisés.</p>
@foreach($templates as $other)<input type="hidden" name="target_versions[{{ $other->id }}]" value="{{ $other->version }}">@endforeach
@else
<input type="hidden" name="inherit_global" value="0"><label class="check"><input type="checkbox" name="inherit_global" value="1" @checked(old('inherit_global',$options['inherit_global'] ?? false))>Utiliser le style commun publié</label>
<p class="muted">Décochez cette option pour personnaliser le style ci-dessous. L’introduction reste propre à ce type de document.</p>
@endif
<div class="actions"><button name="action" value="draft" class="secondary">Enregistrer le brouillon</button><button name="action" value="publish">Publier le modèle</button></div>
</details>
<details class="panel" open><summary>Identité visuelle</summary>
<div class="grid">@foreach(\App\Services\DocumentLayout::COLORS as $key=>$label)<label>{{ $label }}<input type="color" name="{{ $key }}" value="{{ old($key,$options[$key]) }}" required></label>@endforeach
<label>Police<select name="font_family">@foreach(['DejaVu Sans','DejaVu Serif'] as $font)<option @selected(old('font_family',$options['font_family'])===$font)>{{ $font }}</option>@endforeach</select></label>
<label>Taille du texte (pt)<input type="number" name="font_size" min="8" max="12" required value="{{ old('font_size',$options['font_size']) }}"></label></div>
@foreach(['logo'=>'Logo PNG ou JPEG','signature_image'=>'Signature entreprise','stamp_image'=>'Cachet'] as $key=>$label)
<label>{{ $label }} (512 Ko maximum)<input type="file" name="{{ $key }}" accept="image/png,image/jpeg"></label>
@if(!empty($options[$key]))<img class="asset-preview" src="{{ $options[$key] }}" alt="{{ $label }}"><label class="check"><input type="checkbox" name="remove_{{ $key }}" value="1">Retirer cette image du prochain modèle</label>@endif
@endforeach
</details>
<details class="panel"><summary>Fonds de page et marges</summary>
@include('staff.pdf-letterhead')
<p>Dans le modèle premium, les marges s’appliquent aussi sans filigrane. La pagination se place juste sous la zone de contenu. Réservez suffisamment de place pour le graphisme de votre pied de page.</p>
</details>
<details class="panel"><summary>En-tête, contenu et signatures</summary>
<label>Activités<input name="activities" maxlength="300" value="{{ old('activities',$options['activities']) }}"></label>
<label>Introduction<textarea name="body" rows="3" maxlength="10000">{{ old('body',$draft['body'] ?? $template->body) }}</textarea></label>
@foreach(\App\Services\DocumentLayout::TOGGLES as $key=>$label)<input type="hidden" name="{{ $key }}" value="0"><label class="check"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$options[$key]))>{{ $label }}</label>@endforeach
<p class="muted">Si le logo figure déjà dans le filigrane, désactivez son affichage pour éviter de le répéter.</p>
<label>Responsable / mention de signature<input name="signature" maxlength="1000" value="{{ old('signature',$options['signature'] ?? '') }}"></label>
</details>
<details class="panel"><summary>Tableaux et totaux</summary><div class="grid">
@foreach(['table_header'=>'En-tête des tableaux','subtotal_style'=>'Sous-total ouverture','total_style'=>'Total général','discount_style'=>'Remise','deposit_style'=>'Acompte','balance_style'=>'Solde'] as $key=>$label)
<label>{{ $label }}<select name="{{ $key }}">@foreach(['gold'=>'Or','light'=>'Or clair','plain'=>'Blanc'] as $value=>$text)<option value="{{ $value }}" @selected(old($key,$options[$key])===$value)>{{ $text }}</option>@endforeach</select></label>@endforeach
</div></details>
<details class="panel"><summary>Entreprise, conditions et pied de page</summary>
@foreach(['name'=>'Nom de l’entreprise','address'=>'Adresse','registration'=>'Identifiants complémentaires','tax_note'=>'Mention fiscale','payment_terms'=>'Conditions commerciales complémentaires'] as $key=>$label)
<label>{{ $label }}<textarea name="issuer_{{ $key }}" rows="2" maxlength="{{ ['name'=>150,'registration'=>300,'payment_terms'=>2000][$key] ?? 1000 }}" @required($key==='name')>{{ old('issuer_'.$key,$issuer[$key]) }}</textarea></label>@endforeach
<p class="muted">Les taux d’acompte et de solde proviennent du devis. Évitez de les répéter dans les conditions complémentaires.</p>
<div class="grid">@foreach(['issuer_phone'=>'Téléphone','issuer_whatsapp'=>'WhatsApp','issuer_rccm'=>'RCCM','issuer_ncc'=>'NCC','issuer_website'=>'Site internet'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" maxlength="150" value="{{ old($key,$options[$key]) }}"></label>@endforeach</div>
<label>E-mail affiché sur les documents<input type="email" name="issuer_email" maxlength="254" required value="{{ old('issuer_email',$issuer['email']) }}"></label><label>Texte du pied de page<textarea name="footer" maxlength="2000" rows="3">{{ old('footer',$options['footer'] ?? '') }}</textarea></label>
</details>
<section class="panel"><h2>Exemple de prévisualisation</h2><label>Document<select name="preview_type">@foreach(['quote'=>'Devis','invoice'=>'Facture','receipt'=>'Reçu'] as $key=>$label)<option value="{{ $key }}" @selected($key===($template->key==='global'?'quote':$template->key))>{{ $label }}</option>@endforeach</select></label>
<label>Scénario<select name="scenario">@foreach(['single'=>'Une ouverture · 4 × 2 m','four'=>'Quatre fenêtres · référence 271 875 F CFA','eight'=>'Huit ouvertures','twenty'=>'Vingt ouvertures','thirty'=>'Document de plusieurs pages · trente ouvertures','store'=>'Store · 2,45 × 1,80 m','discount'=>'Remise de 10 %','negotiated'=>'Total négocié','individual'=>'Quatre exemplaires individuels'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
<button type="button" id="refresh-preview" class="secondary">Actualiser l’aperçu A4</button><p>Les exemples sont fictifs. Aucun devis, paiement ou message n’est enregistré.</p></section>
</form>
<aside class="layout-preview"><p id="preview-status" role="status" aria-live="polite">Préparation de l’aperçu…</p><a id="preview-download" hidden target="_blank" rel="noopener">Ouvrir le PDF dans un nouvel onglet</a><iframe id="layout-preview" title="Prévisualisation A4 du document"></iframe></aside>
</div>
<details class="panel"><summary>Historique des modèles publiés</summary>
@forelse($revisions as $revision)<form class="actions" method="post" action="{{ route('staff.layouts.restore',[$template->key,$revision->id]) }}">@csrf<input type="hidden" name="version" value="{{ $template->version }}"><span>Version {{ $revision->version }} · {{ $revision->created_at }}</span><button class="secondary">Reprendre en brouillon</button></form>@empty<p>Aucune publication enregistrée pour ce modèle.</p>@endforelse
</details>
<script src="{{ asset('pdf-letterhead.js') }}?v={{ filemtime(public_path('pdf-letterhead.js')) }}" defer></script>
<script src="{{ asset('document-layout.js') }}?v={{ filemtime(public_path('document-layout.js')) }}" defer></script>
@endsection
