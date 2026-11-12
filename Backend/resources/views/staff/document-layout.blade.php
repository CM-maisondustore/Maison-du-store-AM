@extends('staff.layout')
@php $quoteLayout=$quoteLayout ?? null; @endphp
@section('title',$quoteLayout ? 'Mise en page du devis '.\App\Services\StaffInterface::ref($quoteLayout->reference) : 'Mise en page des documents')
@section('back',$quoteLayout ? route('staff.messages.create',['quote_id'=>$quoteLayout->id]) : route('staff.communications.index'))
@section('body-class','has-action-bar')
@section('content')
<link rel="stylesheet" href="{{ asset('document-layout.css') }}?v={{ filemtime(public_path('document-layout.css')) }}">
@php
 $issuer=\App\Services\CommunicationTemplates::issuer($options);
 $activePresentation=\App\Services\DocumentLayout::presentation($template->key);
 $premium=($activePresentation['renderer_version'] ?? 1)===2;
 $blocks=$options['blocks'] ?? null;
 $types=['quote'=>'Devis','invoice'=>'Facture','receipt'=>'Reçu','global'=>'Style commun'];
 $editorData=['types'=>\App\Services\DocumentBlocks::TYPES,'fields'=>\App\Services\DocumentBlocks::FIELDS,'sources'=>\App\Services\DocumentBlocks::SOURCES,
   'columns'=>\App\Services\DocumentBlocks::COLUMNS,'fonts'=>\App\Services\DocumentBlocks::FONTS,'followers'=>\App\Services\DocumentBlocks::FOLLOWERS,
   'labels'=>\App\Services\DocumentBlocks::LABELS,'complement'=>\App\Services\DocumentBlocks::COMPLEMENT_TYPES,
   'margins'=>\App\Services\PdfLetterhead::margins($options),'issuer'=>$issuer['name'] ?? '','kind'=>$template->key==='global' ? 'quote' : $template->key];
@endphp
@if($quoteLayout)
{{-- Mode « ce devis seulement » : la mise en page est enregistrée dans le devis, le modèle commun reste inchangé. --}}
<div class="notice quote-layout-note"><p><strong>Vous modifiez la mise en page de ce devis uniquement.</strong> Le modèle de devis publié reste utilisé pour les autres devis. L’aperçu montre le vrai devis, avec ses montants.</p>
<p class="actions"><a class="button quiet" href="{{ route('staff.quotes.pdf',[$quoteLayout,'apercu'=>1]) }}" target="_blank" rel="noopener">Aperçu du devis enregistré</a><a class="button quiet" href="{{ route('staff.messages.create',['quote_id'=>$quoteLayout->id]) }}">Retour à l’envoi</a>
@if(!empty($quoteLayout->document_metadata['presentation_override']))<button type="submit" form="quote-layout-reset" class="quiet">Reprendre le modèle commun</button>@endif</p></div>
<form id="quote-layout-reset" method="post" action="{{ route('staff.quotes.layout.reset',$quoteLayout) }}" data-confirm="Abandonner la mise en page propre à ce devis et reprendre le modèle de devis publié ?">@csrf @method('DELETE')</form>
@endif
<form id="layout-form" class="pdf-editor" method="post" enctype="multipart/form-data" action="{{ $quoteLayout ? route('staff.quotes.layout.save',$quoteLayout) : route('staff.layouts.save',$template->key) }}" data-preview="{{ $quoteLayout ? route('staff.quotes.layout.preview',$quoteLayout) : route('staff.layouts.preview',$template->key) }}" data-preview-label="{{ $quoteLayout ? 'Aperçu à jour · vrai devis · modifications non enregistrées' : 'Aperçu à jour · données fictives · modifications non publiées' }}">
@csrf<input type="hidden" name="version" value="{{ $template->version }}">@if($quoteLayout)<input type="hidden" name="edit_version" value="{{ $quoteLayout->edit_version ?? 0 }}"><input type="hidden" name="inherit_global" value="0">@endif
{{-- Mise en page libre : JSON validé par le serveur (DocumentBlocks). Vide : le rendu actuel reste utilisé. --}}
<input type="hidden" name="blocks" id="layout-blocks" value="{{ old('blocks',$blocks ? json_encode($blocks,JSON_UNESCAPED_UNICODE) : '') }}">
<script type="application/json" id="layout-editor-data">@json($editorData)</script>
<script type="application/json" id="layout-starter">@json(\App\Services\DocumentBlocks::starter($editorData['margins']))</script>

<header class="editor-bar">
<div class="editor-title">
@if($quoteLayout)<input type="hidden" name="name" value="{{ $template->name }}"><p class="editor-name">Devis {{ $quoteLayout->reference }}</p>@else<label class="sr-only" for="layout-name">Nom du modèle</label><input id="layout-name" class="editor-name" name="name" maxlength="150" required value="{{ old('name',$draft['name'] ?? $template->name) }}">@endif
<p class="editor-state">@if($quoteLayout)<span class="badge {{ !empty($quoteLayout->document_metadata['presentation_override']) ? 'st-progress' : 'st-neutral' }}">{{ !empty($quoteLayout->document_metadata['presentation_override']) ? 'Mise en page propre à ce devis' : 'Modèle de devis publié' }}</span>@endif @if($draft)<span class="badge st-progress"><span aria-hidden="true">◐</span>Brouillon en cours</span>@endif<span class="badge st-ok"><span aria-hidden="true">✓</span>Version publiée{{ $template->published_version ? ' '.$template->published_version : '' }}</span>
@if($template->key!=='global')<span class="badge st-info layout-if-inherit"><span aria-hidden="true">↗</span>Style commun hérité</span><span class="badge st-neutral layout-if-custom"><span aria-hidden="true">○</span>Style personnalisé</span>@endif</p>
</div>
@unless($quoteLayout)<nav class="segmented" aria-label="Type de document">@foreach($types as $key=>$label)<a href="{{ route('staff.layouts.edit',$key) }}" @if($key===$template->key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>@endunless
<div class="segmented" role="group" aria-label="Page affichée" data-editor-only hidden><button type="button" data-view="single" aria-pressed="true">Page unique</button><button type="button" data-view="continuation" aria-pressed="false">Page suite</button></div>
<div class="editor-tools" data-editor-only hidden>
<button type="button" class="secondary icon-btn" data-undo disabled aria-label="Annuler la dernière modification" title="Annuler (Ctrl+Z)">↶</button><button type="button" class="secondary icon-btn" data-redo disabled aria-label="Rétablir" title="Rétablir (Ctrl+Y)">↷</button>
<span class="zoom" role="group" aria-label="Zoom"><button type="button" class="secondary icon-btn" data-zoom="-1" aria-label="Réduire le zoom">−</button><output data-zoom-value aria-live="polite">100 %</output><button type="button" class="secondary icon-btn" data-zoom="1" aria-label="Agrandir le zoom">+</button></span>
</div>
<div class="editor-preview-choice" @if($quoteLayout) hidden @endif>
<label>Aperçu<select name="scenario">@foreach(['single'=>'Une ouverture','four'=>'Quatre fenêtres','eight'=>'Huit ouvertures','twenty'=>'Vingt ouvertures','thirty'=>'Trente ouvertures (plusieurs pages)','store'=>'Store','discount'=>'Remise de 10 %','negotiated'=>'Total négocié','individual'=>'Quatre exemplaires individuels'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
<label>Document<select name="preview_type">@foreach(['quote'=>'Devis','invoice'=>'Facture','receipt'=>'Reçu'] as $key=>$label)<option value="{{ $key }}" @selected($key===($template->key==='global'?'quote':$template->key))>{{ $label }}</option>@endforeach</select></label>
</div>
<div class="editor-save">@if($quoteLayout)<button>Enregistrer pour ce devis</button>@else<button name="action" value="draft" class="secondary">Enregistrer le brouillon</button><button name="action" value="publish">Publier le modèle</button>@endif</div>
</header>

<div class="editor-cols">
<aside class="editor-left" aria-label="Blocs et calques" data-editor-only hidden>
<h2 class="editor-h">Blocs à placer</h2>
<div class="palette">@foreach(['logo','text','company','title','client','object','lines','total','payment','commitment','signature','stamp','footer','shape'] as $type)<button type="button" class="secondary" data-add-block="{{ $type }}">{{ \App\Services\DocumentBlocks::TYPES[$type] }}</button>@endforeach</div>
<h2 class="editor-h" id="h-layers">Calques</h2>
<p class="hint mt-0" data-layers-empty>Aucun bloc sur cette page.</p>
<ol class="layers" data-layers aria-labelledby="h-layers"></ol>
<button type="button" class="quiet" data-reset>Revenir au rendu actuel</button>
</aside>

<section class="editor-center" aria-label="Page A4">
<div class="editor-switch" role="tablist" aria-label="Affichage" data-editor-only hidden><button type="button" role="tab" id="tab-page" aria-controls="editor-stage" aria-selected="true">Page</button><button type="button" role="tab" id="tab-pdf" aria-controls="apercu-a4" aria-selected="false">Aperçu PDF</button></div>
<div class="editor-stage" id="editor-stage" role="tabpanel" aria-labelledby="tab-page" data-editor-only hidden>
<div class="editor-empty" data-empty hidden><p><strong>Ce modèle utilise le rendu actuel.</strong> Les documents gardent leur présentation tant que vous ne placez pas de blocs.</p><button type="button" data-start>Commencer la mise en page libre</button></div>
<div class="sheet-wrap" data-sheet-wrap><div class="ruler ruler-x" data-ruler-x aria-hidden="true"></div><div class="ruler ruler-y" data-ruler-y aria-hidden="true"></div>
<div class="sheet" data-sheet><div class="sheet-margins" data-margins aria-hidden="true"></div><div class="sheet-blocks" data-blocks></div></div></div>
<p class="sr-only" id="block-help">Flèches : déplacer de 1 mm, Maj + flèches : 5 mm, Alt + flèches : redimensionner. Suppr : retirer le bloc.</p>
</div>
<noscript><p class="notice">L’éditeur de blocs demande JavaScript. Les réglages du document restent modifiables et s’enregistrent ; la mise en page enregistrée est conservée.</p></noscript>
<aside class="layout-preview" id="apercu-a4" data-on-demand role="tabpanel" aria-labelledby="tab-pdf" aria-label="Aperçu du document"><div class="preview-head"><p id="preview-status" class="muted" role="status" aria-live="polite">Préparation de l’aperçu…</p><span class="preview-actions"><a id="preview-download" class="link" hidden target="_blank" rel="noopener">Ouvrir le PDF dans un nouvel onglet</a><button type="button" id="refresh-preview" class="secondary">Actualiser l’aperçu A4</button></span></div><iframe id="layout-preview" title="Prévisualisation A4 du document"></iframe></aside>
</section>

<aside class="editor-right" aria-label="Propriétés">
<section class="block-props" data-props hidden aria-labelledby="h-props">
<p class="muted mt-0">Bloc sélectionné</p><h2 id="h-props" class="props-title" data-prop-title></h2>
<div class="grid props-grid">
@foreach(['x'=>'X','y'=>'Y','w'=>'Largeur','h'=>'Hauteur'] as $key=>$label)<label>{{ $label }}<span class="field-unit"><input type="number" step="any" data-prop="{{ $key }}" inputmode="decimal"><span class="unit">mm</span></span></label>@endforeach
</div>
<div class="prop-row" role="group" aria-label="Alignement du texte">@foreach(['left'=>'À gauche','center'=>'Centré','right'=>'À droite'] as $value=>$label)<button type="button" class="secondary" data-align="{{ $value }}" aria-pressed="false">{{ $label }}</button>@endforeach</div>
<label class="check"><input type="checkbox" data-prop="locked">Verrouiller la position</label>
<details class="fold" open><summary>Texte</summary>
<div class="grid props-grid">
<label class="mt-0">Police<select data-prop="font">@foreach(\App\Services\DocumentBlocks::FONTS as $font)<option>{{ $font }}</option>@endforeach</select></label>
<label class="mt-0">Taille<span class="field-unit"><input type="number" step="any" data-prop="size" inputmode="numeric"><span class="unit">pt</span></span></label>
<label>Couleur<input type="color" data-prop="color"></label>
</div>
<div data-for-types="{{ implode(' ',array_keys(\App\Services\DocumentBlocks::LABELS)) }}"><label>Intitulé<input data-prop="label" maxlength="120" data-label-input></label><p class="hint">Laissez vide pour garder l’intitulé proposé.</p></div>
<div data-for-types="text commitment {{ implode(' ',\App\Services\DocumentBlocks::COMPLEMENT_TYPES) }}"><label><span data-text-label>Contenu</span><textarea rows="4" maxlength="2000" data-prop="text"></textarea></label>
<p class="hint" data-text-hint></p>
<p class="hint">Données à insérer, remplacées dans chaque document :</p><div class="chips field-chips">@foreach(\App\Services\DocumentBlocks::FIELDS as $field)<button type="button" class="chip" data-insert="{{ $field }}">{{ $field }}</button>@endforeach</div></div>
</details>
<details class="fold" data-for-types="lines" open><summary>Tableau<small>Colonnes et enchaînement des pages</small></summary>
<div class="prop-row">@foreach(\App\Services\DocumentBlocks::COLUMNS as $key=>$label)<label class="check"><input type="checkbox" data-column="{{ $key }}">{{ $label }}</label>@endforeach</div>
<label class="toggle-row"><span>Regrouper par ouverture<small>Avec un sous-total sous chaque ouverture</small></span><input class="switch" type="checkbox" data-prop="group"></label>
<label class="toggle-row"><span>Total juste sous les lignes<small>Pose et livraison, puis total et conditions de paiement</small></span><input class="switch" type="checkbox" data-prop="follow"></label>
<p class="hint">Un document trop long continue toujours sur des pages suite, qui reprennent les blocs marqués « Pages suite ».</p>
</details>
<label data-for-types="logo">Image<select data-prop="source">@foreach(\App\Services\DocumentBlocks::SOURCES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
<label data-for-types="shape">Forme<select data-prop="shape"><option value="frame">Cadre</option><option value="line">Ligne</option></select></label>
<details class="fold"><summary>Fond et bordure</summary>
<label class="check"><input type="checkbox" data-prop="has_background">Fond coloré</label>
<label>Couleur du fond<input type="color" data-prop="background"></label>
<div class="grid props-grid">
<label>Bordure<span class="field-unit"><input type="number" step="any" data-prop="border_width" inputmode="decimal"><span class="unit">mm</span></span></label>
<label>Couleur<input type="color" data-prop="border_color"></label>
</div>
</details>
<details class="fold" open><summary>Pages où il apparaît</summary>
<label class="check"><input type="checkbox" data-page-kind="single">Page unique ou dernière page</label>
<label class="check"><input type="checkbox" data-page-kind="continuation">Pages suite</label>
</details>
<div class="actions"><button type="button" class="quiet" data-remove-block>Retirer ce bloc</button><button type="button" class="secondary" data-deselect>Terminer</button></div>
</section>

<section class="doc-settings" aria-labelledby="h-doc-settings">
<h2 id="h-doc-settings" class="editor-h">Réglages du document</h2>
@unless($quoteLayout)<details class="fold" @if($errors->hasAny(['name','inherit_global','apply_all'])) open @endif><summary>Modèle<small>Style commun et application aux autres documents</small></summary>
@if($template->key==='global')
<input type="hidden" name="apply_all" value="0"><label class="toggle-row"><span>Appliquer le style commun aux devis, factures et reçus lors de la publication<small>Les modèles déjà rattachés suivent toujours le style commun. Cette option rattache aussi les modèles personnalisés.</small></span><input class="switch" type="checkbox" name="apply_all" value="1" checked></label>
@foreach($templates as $other)<input type="hidden" name="target_versions[{{ $other->id }}]" value="{{ $other->version }}">@endforeach
@else
<input type="hidden" name="inherit_global" value="0"><label class="toggle-row"><span>Utiliser le style commun publié<small>Décochez cette option pour personnaliser le style et la mise en page. L’introduction reste propre à ce type de document.</small></span><input class="switch" type="checkbox" name="inherit_global" value="1" @checked(old('inherit_global',$options['inherit_global'] ?? false))></label>
@endif
<p class="muted">Version de travail {{ $template->version }} · {{ $premium ? 'Modèle premium publié' : 'Réglages historiques conservés' }}@if(isset($activePresentation['global_template_version'])) · style commun v{{ $activePresentation['global_template_version'] }}@endif. {{ $draft ? 'La version publiée reste utilisée pour les documents.' : 'Préparez un brouillon, vérifiez le PDF puis publiez le modèle.' }}</p>
</details>@endunless
<details class="fold"><summary>Identité<small>Couleurs, police, logo, signature et cachet</small></summary>
<div class="grid">@foreach(\App\Services\DocumentLayout::COLORS as $key=>$label)<label class="swatch">{{ $label }}<input type="color" name="{{ $key }}" value="{{ old($key,$options[$key]) }}" required></label>@endforeach</div>
<div class="grid">
<label>Police<select name="font_family">@foreach(\App\Services\DocumentBlocks::FONTS as $font)<option @selected(old('font_family',$options['font_family'])===$font)>{{ $font }}</option>@endforeach</select></label>
<label>Taille du texte<span class="field-unit"><input type="number" name="font_size" min="8" max="12" required value="{{ old('font_size',$options['font_size']) }}"><span class="unit">pt</span></span></label>
</div>
@foreach(['logo'=>'Logo PNG ou JPEG','signature_image'=>'Signature entreprise','stamp_image'=>'Cachet'] as $key=>$label)
<label>{{ $label }} (512 Ko maximum)<input type="file" name="{{ $key }}" accept="image/png,image/jpeg"></label>
@if(!empty($options[$key]))<img class="asset-preview" src="{{ $options[$key] }}" alt="{{ $label }}" data-asset="{{ $key }}"><label class="check"><input type="checkbox" name="remove_{{ $key }}" value="1">Retirer cette image du prochain modèle</label>@endif
@endforeach
</details>
<details class="fold" @if($errors->hasAny(['letterhead_standard','letterhead_continuation','margin_top_mm','margin_bottom_mm','margin_left_mm','margin_right_mm','letterhead_opacity'])) open @endif><summary>Fonds et marges<small>Filigranes et zone imprimable</small></summary>
@include('staff.pdf-letterhead')
<p class="muted">Avec la mise en page libre, les marges servent de repères à l’éditeur : un bloc qui en sort apparaît en rouge.</p>
</details>
<details class="fold"><summary>Contenu<small>Introduction, éléments affichés, signature</small></summary>
<label class="mt-0">Activités<input name="activities" maxlength="300" value="{{ old('activities',$options['activities']) }}"></label>
<label>Introduction<textarea name="body" rows="3" maxlength="10000">{{ old('body',$quoteLayout ? ($quoteBody ?? '') : ($draft['body'] ?? $template->body)) }}</textarea></label>
@foreach(\App\Services\DocumentLayout::TOGGLES as $key=>$label)<input type="hidden" name="{{ $key }}" value="0"><label class="toggle-row"><span>{{ $label }}</span><input class="switch" type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$options[$key]))></label>@endforeach
<p class="muted">Si le logo figure déjà dans le filigrane, désactivez son affichage pour éviter de le répéter.</p>
<label>Responsable / mention de signature<input name="signature" maxlength="1000" value="{{ old('signature',$options['signature'] ?? '') }}"></label>
</details>
<details class="fold"><summary>Tableaux et totaux<small>Couleurs des en-têtes et des montants</small></summary>
<div class="grid">
@foreach(['table_header'=>'En-tête des tableaux','subtotal_style'=>'Sous-total ouverture','total_style'=>'Total général','discount_style'=>'Remise','deposit_style'=>'Acompte','balance_style'=>'Solde'] as $key=>$label)
<label class="mt-0">{{ $label }}<select name="{{ $key }}">@foreach(['gold'=>'Or','light'=>'Or clair','plain'=>'Blanc'] as $value=>$text)<option value="{{ $value }}" @selected(old($key,$options[$key])===$value)>{{ $text }}</option>@endforeach</select></label>@endforeach
</div>
</details>
<details class="fold" @if($errors->hasAny(['issuer_name','issuer_email'])) open @endif><summary>Entreprise<small>Mentions légales, conditions, pied de page</small></summary>
@foreach(['name'=>'Nom de l’entreprise','address'=>'Adresse','registration'=>'Identifiants complémentaires','tax_note'=>'Mention fiscale','payment_terms'=>'Conditions commerciales complémentaires'] as $key=>$label)
<label @if($loop->first) class="mt-0" @endif>{{ $label }}<textarea name="issuer_{{ $key }}" rows="2" maxlength="{{ ['name'=>150,'registration'=>300,'payment_terms'=>2000][$key] ?? 1000 }}" @required($key==='name')>{{ old('issuer_'.$key,$issuer[$key]) }}</textarea></label>@endforeach
<p class="muted">Les taux d’acompte et de solde proviennent du devis. Évitez de les répéter dans les conditions complémentaires.</p>
<div class="grid">@foreach(['issuer_phone'=>'Téléphone','issuer_whatsapp'=>'WhatsApp','issuer_rccm'=>'RCCM','issuer_ncc'=>'NCC','issuer_website'=>'Site internet'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" maxlength="150" value="{{ old($key,$options[$key]) }}"></label>@endforeach</div>
<label>Notre engagement<textarea name="commitment_text" maxlength="1000" rows="3">{{ old('commitment_text',$options['commitment_text'] ?? '') }}</textarea><span class="hint">Imprimé sous les conditions de paiement des devis à échéancier, et dans le bloc « Notre engagement » laissé vide.</span></label>
<label>E-mail affiché sur les documents<input type="email" name="issuer_email" maxlength="254" required value="{{ old('issuer_email',$issuer['email']) }}"></label><label>Texte du pied de page<textarea name="footer" maxlength="2000" rows="3">{{ old('footer',$options['footer'] ?? '') }}</textarea></label>
</details>
</section>
</aside>
</div>
</form>
@unless($quoteLayout)<details class="panel mt-12"><summary>Historique des modèles publiés</summary>
@forelse($revisions as $revision)<form class="actions" method="post" action="{{ route('staff.layouts.restore',[$template->key,$revision->id]) }}">@csrf<input type="hidden" name="version" value="{{ $template->version }}"><span>Version {{ $revision->version }}, <span class="mono">{{ $revision->created_at }}</span></span><button class="secondary">Reprendre en brouillon</button></form>@empty<p>Aucune publication enregistrée pour ce modèle.</p>@endforelse
</details>@endunless
<div class="action-bar editor-foot"><span class="status"><span data-editor-only hidden>Grille de 5 mm aimantée · Marges : haut {{ $editorData['margins']['top'] }} mm, bas {{ $editorData['margins']['bottom'] }} mm, gauche {{ $editorData['margins']['left'] }} mm, droite {{ $editorData['margins']['right'] }} mm · Les blocs rouges sortent de la zone imprimable. </span>{{ $quoteLayout ? 'Enregistrer s’applique à ce devis seulement ; les PDF déjà émis restent inchangés.' : 'Publier s’applique aux prochains documents uniquement ; les PDF déjà émis restent inchangés.' }}</span></div>
<script src="{{ asset('pdf-letterhead.js') }}?v={{ filemtime(public_path('pdf-letterhead.js')) }}" defer></script>
<script src="{{ asset('document-layout.js') }}?v={{ filemtime(public_path('document-layout.js')) }}" defer></script>
<script src="{{ asset('document-editor.js') }}?v={{ filemtime(public_path('document-editor.js')) }}" defer></script>
@endsection
