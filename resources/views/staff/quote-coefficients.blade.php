{{-- Étape 3 : coefficients, confection et pose communs. Ces contrôles remplissent les champs de chaque ouverture et les frais du devis ;
     ils ne sont pas enregistrés eux-mêmes. Sans JavaScript, ils restent cachés et les champs par ouverture servent directement. --}}
<div class="calc-form" data-calc-form hidden>
<h3 class="choice-title">Coefficients</h3>
<p class="hint">Appliqués à toutes les ouvertures quand le métrage est vide. Chaque ouverture reste modifiable à l’étape 2.</p>
@foreach(['material'=>['Tissu',['2','2.5','3']],'secondary'=>['Voilage',['2','2.5','3']],'tape'=>['Ruflette',['1','1.5','2']]] as $key=>[$label,$values])
<div class="choice-line" data-coef="{{ $key }}" role="radiogroup" aria-labelledby="coef-{{ $key }}"><span class="choice-name" id="coef-{{ $key }}">{{ $label }}</span>
<div class="chip-set">@foreach($values as $value)<label class="chip-radio"><input type="radio" name="coefficient_ui[{{ $key }}]" value="{{ $value }}"><span>{{ str_replace('.',',',$value) }}</span></label>@endforeach
<label class="chip-radio"><input type="radio" name="coefficient_ui[{{ $key }}]" value="custom"><span>Saisir</span></label>
<label class="coef-custom"><span class="sr-only">{{ $label }} : coefficient saisi</span><input inputmode="decimal" name="coefficient_ui[{{ $key }}_custom]" maxlength="6" placeholder="ex. 2,2" disabled></label>
<small class="hint" data-coef-note></small></div>
</div>
@endforeach
<h3 class="choice-title mt-12">Confection</h3>
<p class="hint">Auto : le tarif de confection du catalogue, sur la quantité coefficiée. Saisir : votre prix à l’unité pour toutes les ouvertures concernées.</p>
@foreach(['curtain'=>['Confection tissus','quantité coefficiée','Prix par mètre'],'sheer'=>['Confection voilage','quantité coefficiée','Prix par mètre'],'store'=>['Confection store','quantité en m²','Prix par m²']] as $key=>[$label,$basis,$placeholder])
<div class="choice-line" data-sewing="{{ $key }}" role="radiogroup" aria-labelledby="sew-{{ $key }}" hidden><span class="choice-name" id="sew-{{ $key }}">{{ $label }}<small class="choice-sub">{{ $basis }} : <span data-sewing-qty>—</span></small></span>
<div class="chip-set"><label class="chip-radio"><input type="radio" name="sewing_ui[{{ $key }}]" value="auto"><span>Auto</span></label><label class="chip-radio"><input type="radio" name="sewing_ui[{{ $key }}]" value="manual"><span>Saisir</span></label><label class="chip-radio"><input type="radio" name="sewing_ui[{{ $key }}]" value="none"><span>Non</span></label>
<label class="field-unit is-price coef-custom"><span class="sr-only">{{ $label }} : prix saisi</span><input type="number" min="0" max="100000000" step="1" name="sewing_ui_price[{{ $key }}]" placeholder="{{ $placeholder }}" disabled><span class="unit">FCFA</span></label>
<small class="hint" data-sewing-note></small></div>
</div>
@endforeach
<h3 class="choice-title mt-12">Pose et livraison</h3>
<div class="choice-line" data-zone-line role="radiogroup" aria-labelledby="zone-title"><span class="choice-name" id="zone-title">Zone de pose</span>
<div class="chip-set"><label class="chip-radio"><input type="radio" name="zone_ui" value="abidjan"><span>Abidjan</span></label><label class="chip-radio"><input type="radio" name="zone_ui" value="other"><span>Hors Abidjan</span></label>
<small class="hint" data-zone-note>Reprise de la commune de l’étape 1. Hors Abidjan : pose à chiffrer.</small></div>
</div>
<div class="choice-line" data-install role="radiogroup" aria-labelledby="install-title"><span class="choice-name" id="install-title">Pose et livraison<small class="choice-sub">total ouvertures : <span data-install-count>—</span></small></span>
<div class="chip-set"><label class="chip-radio"><input type="radio" name="install_ui" value="auto"><span>Valeurs auto</span></label><label class="chip-radio"><input type="radio" name="install_ui" value="manual"><span>Saisir</span></label>
<label class="field-unit is-price coef-custom"><span class="sr-only">Pose et livraison : prix saisi</span><input type="number" min="0" max="100000000" step="1" name="install_ui_price" placeholder="Prix du forfait" disabled><span class="unit">FCFA</span></label></div>
<small class="hint" data-install-note>Valeurs auto : la pose du catalogue, ouverture par ouverture. Saisir : un forfait unique pour tout le devis.</small>
</div>
</div>
