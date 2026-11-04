@php($kindNow=old('kind',$contact->kind ?: 'client'))
<div class="fields-col">
<section class="fgroup">
<fieldset class="bare"><legend class="section-title"><span class="step" aria-hidden="true">1</span>Catégorie</legend>
<div class="tile-group kind-tiles">
@foreach(['client'=>['Client','Particulier ou entreprise'],'fournisseur'=>['Fournisseur','Tissus, rails, moteurs'],'partenaire'=>['Partenaire','Architecte, décorateur']] as $value=>$kind)<label class="tile"><input type="radio" name="kind" value="{{ $value }}" @checked($kindNow===$value)><span><strong>{{ $kind[0] }}</strong><small>{{ $kind[1] }}</small></span></label>
@endforeach</div>
</fieldset>
</section>
<section class="fgroup">
<h3 class="section-title"><span class="step" aria-hidden="true">2</span>Coordonnées</h3>
<label>Nom / raison sociale<input name="name" required maxlength="200" autocomplete="off" value="{{ old('name', $contact->name) }}"></label>
<label>Téléphone<input type="tel" name="phone" maxlength="50" autocomplete="off" value="{{ old('phone', $contact->phone) }}"></label>
<label>E-mail<input type="email" name="email" maxlength="200" autocomplete="off" value="{{ old('email', $contact->email) }}"><span class="hint">Facultatif. Chaque adresse ne sert qu’à un seul dossier.</span>@error('email')<a class="hint link" href="{{ route('staff.contacts.index',['q'=>old('email')]) }}">Rechercher le dossier qui utilise cette adresse</a>@enderror</label>
<label>Adresse<textarea name="address" rows="2" maxlength="1000">{{ old('address', $contact->address) }}</textarea></label>
</section>
</div>
<div class="fields-col">
<section class="fgroup" data-consent>
<h3 class="section-title"><span class="step" aria-hidden="true">3</span>Promotions</h3>
<label class="check"><input type="checkbox" name="marketing" value="1" @checked(old('marketing', (bool)$contact->marketing_consent_at))> Consentement aux offres et promotions recueilli</label>
<label class="consent-source">Origine du consentement<input name="consent_source" maxlength="250" placeholder="Ex. accord écrit du 24/09/2026" value="{{ old('consent_source', $contact->consent_source) }}"><span class="hint">Obligatoire dès que le consentement est coché.</span></label>
</section>
<div class="internal-block">
<p class="internal-title">@include('staff.partials.icon',['n'=>'lock','s'=>16])Réservé à l’équipe</p>
<label>Notes internes<textarea name="notes" rows="4" maxlength="10000" placeholder="Préférences, historique, points d’attention…">{{ old('notes', $contact->notes) }}</textarea><span class="hint">Jamais transmises au client.</span></label>
</div>
@if($contact->exists)<label class="check mt-12">
<input type="checkbox" name="archived" value="1" @checked(old('archived', (bool)$contact->archived_at))> Dossier archivé (historique conservé)</label>
@endif
</div>
