<section class="panel" data-agent-window>
<div class="actions"><h3>Ouverture</h3><button type="button" class="secondary" data-remove-opening>Retirer cette ouverture</button></div>
<div class="grid"><label>Pièce / description de l’ouverture<input required name="windows[{{ $index }}][label]" maxlength="100" value="{{ $window['label'] ?? '' }}" placeholder="Salon — ouverture 1"></label>
@foreach(['width'=>'Largeur','height'=>'Hauteur'] as $key=>$label)<label>{{ $label }} (cm)<input required inputmode="decimal" name="windows[{{ $index }}][{{ $key }}]" value="{{ $window[$key] ?? '' }}"></label>@endforeach</div>
<input type="hidden" name="windows[{{ $index }}][inherit_defaults]" value="0"><label class="check"><input type="checkbox" data-agent-inherit name="windows[{{ $index }}][inherit_defaults]" value="1" @checked($window['inherit_defaults'] ?? true)>Utiliser les choix communs</label>
@include('staff.agent-quote-selection',['prefix'=>'windows['.$index.']','selection'=>$window])
<label>Description / précisions<textarea name="windows[{{ $index }}][notes]" maxlength="1000">{{ $window['notes'] ?? '' }}</textarea></label>
</section>
