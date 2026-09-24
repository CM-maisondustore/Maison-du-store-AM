<div class="grid">
<label>Agent<select name="agent_id" required>
<option value="">Choisir un agent</option>
@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('agent_id',$job->agent_id)==$agent->id)>{{ $agent->name }}</option>
@endforeach</select>
</label>
<label>Date / heure (Abidjan)<input name="scheduled_at" type="datetime-local" required value="{{ old('scheduled_at',$job->scheduled_at?->format('Y-m-d\TH:i')) }}">
</label>
<label>Durée (minutes)<input name="duration_minutes" type="number" min="15" max="480" required value="{{ old('duration_minutes',$job->duration_minutes ?? 60) }}">
</label>
</div>
<label>Adresse d’intervention<textarea name="address" required>{{ old('address',$job->address) }}</textarea>
</label>
<label>Consignes<textarea name="instructions">{{ old('instructions',$job->instructions) }}</textarea>
</label>
