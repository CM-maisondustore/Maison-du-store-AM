@php($stepped=$stepped ?? false)
@if($stepped)<section class="fgroup"><h3 class="section-title"><span class="step" aria-hidden="true">3</span>Agent et créneau</h3>@endif
<div class="grid">
<label>Agent<select name="agent_id" required>
<option value="">Choisir un agent</option>
@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('agent_id',$job->agent_id)==$agent->id)>{{ $agent->name }}</option>
@endforeach</select>
</label>
<label>Date / heure (Abidjan)<input name="scheduled_at" type="datetime-local" required value="{{ old('scheduled_at',$job->scheduled_at?->format('Y-m-d\TH:i')) }}">
</label>
<label>Durée<span class="field-unit"><input name="duration_minutes" type="number" inputmode="numeric" min="15" max="480" required value="{{ old('duration_minutes',$job->duration_minutes ?? 60) }}"><span class="unit">minutes</span></span>
</label>
</div>
@if($stepped)<p class="hint">Un agent ne peut pas avoir deux prestations sur le même créneau.</p></section>
<section class="fgroup"><h3 class="section-title"><span class="step" aria-hidden="true">4</span>Adresse et consignes</h3>@endif
<label>Adresse d’intervention<textarea name="address" rows="2" required>{{ old('address',$job->address) }}</textarea>@if($stepped)<span class="hint">Reprise du dossier client quand le champ est vide.</span>@endif
</label>
<label>Consignes<textarea name="instructions" rows="3">{{ old('instructions',$job->instructions) }}</textarea>
</label>
@if($stepped)</section>@endif
