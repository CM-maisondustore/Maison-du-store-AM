@php($pricing=old('pricing',$quote->configuration['calculation']['pricing'] ?? []))
@php($feesInUse=collect($pricing['fees'] ?? [])->contains(fn($fee)=>!empty($fee['enabled'])))
<input type="hidden" name="pricing[engine]" value="2">
<input type="hidden" name="use_catalogue" value="1">
<div class="grid">
<label>Présentation des ouvertures<select name="pricing[display_mode]"><option value="grouped" @selected(($pricing['display_mode'] ?? 'grouped')==='grouped')>Regroupées avec nombre d’exemplaires</option><option value="individual" @selected(($pricing['display_mode'] ?? '')==='individual')>Détaillées individuellement</option></select></label>
<label>Remise<select name="pricing[discount_type]">@foreach(['none'=>'Aucune','percentage'=>'Pourcentage','fixed'=>'Montant fixe','target_total'=>'Total négocié'] as $key=>$label)<option value="{{ $key }}" @selected(($pricing['discount_type'] ?? 'none')===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Valeur de remise / total négocié<input name="pricing[discount_value]" inputmode="decimal" value="{{ $pricing['discount_value'] ?? 0 }}"></label>
</div>
<details class="fold" @if($feesInUse || $errors->has('pricing.fees')) open @endif>
<summary>Frais du devis<small>Pose, livraison, transport, déplacement : facturés une seule fois par défaut</small></summary>
<p class="muted mt-0">Pour une pose globale, décochez les poses par ouverture. Zéro est accepté pour un poste offert.</p>
@foreach(\App\Services\Quotes\PricingConfig::FEES as $key=>$label)
@php($fee=$pricing['fees'][$key] ?? [])
<div class="grid fee-row">
<label class="check"><input type="checkbox" name="pricing[fees][{{ $key }}][enabled]" value="1" @checked($fee['enabled'] ?? false)> {{ $label }}</label>
<label>Tarif du catalogue<select name="pricing[fees][{{ $key }}][option_id]"><option value="">Prix libre</option>@foreach(collect($catalogue['options'])->where('kind',$key==='installation_delivery'?'combined':$key)->where('unit','flat')->where('active',true) as $option)<option value="{{ $option['id'] }}" @selected(($fee['option_id'] ?? null)==$option['id'])>{{ $option['label'] }} · {{ $option['category'] }}</option>@endforeach</select></label>
<label>Prix personnalisé<span class="field-unit is-price"><input type="number" name="pricing[fees][{{ $key }}][unit_price]" min="0" max="100000000" step="1" value="{{ array_key_exists('price_override',$fee) ? ($fee['price_override'] ?? '') : ($fee['unit_price'] ?? '') }}" placeholder="Tarif du catalogue ou à définir"><span class="unit">FCFA</span></span></label>
<label>Application<select name="pricing[fees][{{ $key }}][scope]">@foreach(['global'=>'Une seule fois','per_opening'=>'Par ouverture','per_trip'=>'Par trajet'] as $scope=>$text)<option value="{{ $scope }}" @selected(($fee['scope'] ?? 'global')===$scope)>{{ $text }}</option>@endforeach</select></label>
<label>Nombre de trajets<input type="number" name="pricing[fees][{{ $key }}][quantity]" min="1" max="1000" step="1" value="{{ $fee['quantity'] ?? 1 }}"></label>
</div>
@endforeach
</details>
