@php($hasMeasures=!empty($window['width']) || !empty($window['height']))
<section class="panel opening" data-agent-window>
<details class="opening-card" open>
<summary><span class="opening-name" data-opening-name>{{ $window['label'] ?? 'Ouverture' }}</span><span class="opening-sum" data-opening-sum>{{ $hasMeasures ? ($window['width'] ?? '?').' × '.($window['height'] ?? '?').' cm' : 'Mesures à saisir' }}</span></summary>
<div class="opening-body">
<div class="opening-top"><span class="opening-shape" data-opening-shape aria-hidden="true"><i></i></span><label>Désignation<input class="opening-title" required name="windows[{{ $index }}][label]" maxlength="100" value="{{ $window['label'] ?? '' }}" placeholder="Salon — fenêtre sur rue"></label></div>
<div class="opening-measures">
@foreach(['width'=>'Largeur','height'=>'Hauteur'] as $key=>$label)<label>{{ $label }}<span class="field-unit measure"><input required inputmode="decimal" name="windows[{{ $index }}][{{ $key }}]" value="{{ $window[$key] ?? '' }}"><span class="unit">cm</span></span></label>@endforeach
</div>
<label>Notes de l’ouverture<textarea name="windows[{{ $index }}][notes]" rows="2" maxlength="1000" placeholder="Accès, prise électrique, contrainte de pose…">{{ $window['notes'] ?? '' }}</textarea></label>
@include('staff.opening-photo')
<input type="hidden" name="windows[{{ $index }}][inherit_defaults]" value="0"><label class="check"><input type="checkbox" data-agent-inherit name="windows[{{ $index }}][inherit_defaults]" value="1" @checked($window['inherit_defaults'] ?? true)>Utiliser les choix communs</label>
<details class="quote-opening-details"><summary>Type, motorisation, finition et zone de pose</summary>
@include('staff.agent-quote-selection',['prefix'=>'windows['.$index.']','selection'=>$window])
</details>
<p class="hint">Pour les rideaux ou voilages de plus de 2,80 m, Admin-CM effectuera le calcul manuellement.</p>
<div class="opening-foot"><button type="button" class="quiet" data-remove-opening>Retirer cette ouverture</button></div>
</div>
</details>
</section>
