@php($hasMeasures=!empty($window['width']) || !empty($window['height']))
<section class="panel opening" data-agent-window>
<details class="opening-card" open>
<summary><span class="opening-name" data-opening-name>{{ $window['label'] ?? 'Ouverture' }}</span><span class="opening-sum" data-opening-sum>{{ $hasMeasures ? ($window['width'] ?? '?').' × '.($window['height'] ?? '?').' cm' : 'Mesures à saisir' }}</span></summary>
<div class="opening-body">
<label>Pièce / description de l’ouverture<input required name="windows[{{ $index }}][label]" maxlength="100" value="{{ $window['label'] ?? '' }}" placeholder="Salon — ouverture 1"></label>
<div class="opening-measures">
@foreach(['width'=>'Largeur','height'=>'Hauteur'] as $key=>$label)<label>{{ $label }}<span class="field-unit measure"><input required inputmode="decimal" name="windows[{{ $index }}][{{ $key }}]" value="{{ $window[$key] ?? '' }}"><span class="unit">cm</span></span></label>@endforeach
</div>
<input type="hidden" name="windows[{{ $index }}][inherit_defaults]" value="0"><label class="check"><input type="checkbox" data-agent-inherit name="windows[{{ $index }}][inherit_defaults]" value="1" @checked($window['inherit_defaults'] ?? true)>Utiliser les choix communs</label>
<details class="quote-opening-details"><summary>Type, motorisation, finition et zone de pose</summary>
@include('staff.agent-quote-selection',['prefix'=>'windows['.$index.']','selection'=>$window])
<label>Description / précisions<textarea name="windows[{{ $index }}][notes]" maxlength="1000">{{ $window['notes'] ?? '' }}</textarea></label>
</details>
<p class="hint">Pour les rideaux ou voilages de plus de 2,80 m, Admin-CM effectuera le calcul manuellement.</p>
<div class="opening-foot"><button type="button" class="quiet" data-remove-opening>Retirer cette ouverture</button></div>
</div>
</details>
</section>
