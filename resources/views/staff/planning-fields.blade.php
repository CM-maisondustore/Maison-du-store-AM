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
@if($stepped)<div class="chips duration-chips" data-duration-chips hidden role="group" aria-label="Durées courantes">@foreach([30=>'30 min',60=>'1 h',120=>'2 h',240=>'4 h'] as $minutes=>$label)<button type="button" class="chip" data-minutes="{{ $minutes }}" aria-pressed="false">{{ $label }}</button>@endforeach</div>
{{-- Planning de l'agent ce jour-là, lu dans #agent-planning (admin.js). Sans JavaScript, le serveur refuse toujours un chevauchement. --}}
<div class="agent-day" data-agent-day hidden>
<p class="agent-day-title" data-day-title></p>
<ul class="day-track" data-day-track></ul>
<div class="day-scale" aria-hidden="true">@foreach(['08:00','10:00','12:00','14:00','16:00','18:00'] as $hour)<span>{{ $hour }}</span>@endforeach</div>
<p class="day-free" data-day-free aria-live="polite"><span data-day-free-text></span> <button type="button" class="quiet" data-day-pick hidden></button></p>
</div>
<p class="hint">Un agent ne peut pas avoir deux prestations sur le même créneau.</p></section>
<section class="fgroup"><h3 class="section-title"><span class="step" aria-hidden="true">4</span>Adresse et consignes</h3>@endif
<label>Adresse d’intervention<textarea name="address" rows="2" required>{{ old('address',$job->address) }}</textarea>@if($stepped)<span class="hint">Reprise du dossier client quand le champ est vide.</span>@endif
</label>
<label>Consignes<textarea name="instructions" rows="3">{{ old('instructions',$job->instructions) }}</textarea>
</label>
@if($stepped)</section>@endif
