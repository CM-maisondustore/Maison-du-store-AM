<div class="grid">
<label>Nom / raison sociale<input name="name" required maxlength="200" value="{{ old('name', $contact->name) }}">
</label>
<label>Catégorie<select name="kind">
@foreach(['client'=>'Client','fournisseur'=>'Fournisseur','partenaire'=>'Partenaire'] as $value=>$label)<option value="{{ $value }}" @selected(old('kind', $contact->kind) === $value)>{{ $label }}</option>
@endforeach</select>
</label>
<label>E-mail<input type="email" name="email" value="{{ old('email', $contact->email) }}">
</label>
<label>Téléphone<input name="phone" value="{{ old('phone', $contact->phone) }}">
</label>
</div>
<label>Adresse<textarea name="address">{{ old('address', $contact->address) }}</textarea>
</label>
<label>Notes internes<textarea name="notes">{{ old('notes', $contact->notes) }}</textarea>
</label>
<label class="check">
<input type="checkbox" name="marketing" value="1" @checked(old('marketing', (bool)$contact->marketing_consent_at))> Consentement aux offres et promotions recueilli</label>
<label>Origine du consentement<input name="consent_source" placeholder="Ex. accord écrit du 24/09/2026" value="{{ old('consent_source', $contact->consent_source) }}">
</label>
@if($contact->exists)<label class="check">
<input type="checkbox" name="archived" value="1" @checked(old('archived', (bool)$contact->archived_at))> Dossier archivé (historique conservé)</label>
@endif
