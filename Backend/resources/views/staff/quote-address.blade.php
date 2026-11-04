{{-- Étape 1 : commune d'Abidjan (contact[city]) et complément (contact[address]). Une ancienne ville hors liste reste proposée, sélectionnée. --}}
@php
 $commune=old('contact.city',$quote->contact['city'] ?? '');
 $communes=[...\App\Services\StaffInterface::COMMUNES,\App\Services\StaffInterface::OUTSIDE_ABIDJAN];
@endphp
<label>Commune<select name="contact[city]" required data-commune>
<option value="">Choisir la commune</option>
@if($commune!=='' && !in_array($commune,$communes,true))<option value="{{ $commune }}" selected>{{ $commune }}</option>@endif
<optgroup label="Abidjan">@foreach(\App\Services\StaffInterface::COMMUNES as $name)<option value="{{ $name }}" @selected($commune===$name)>{{ $name }}</option>@endforeach</optgroup>
<option value="{{ \App\Services\StaffInterface::OUTSIDE_ABIDJAN }}" @selected($commune===\App\Services\StaffInterface::OUTSIDE_ABIDJAN)>{{ \App\Services\StaffInterface::OUTSIDE_ABIDJAN }}</option>
</select></label>
<label class="wide">{{ $addressLabel ?? 'Complément d’adresse' }}<input name="contact[address]" type="text" required maxlength="1000" value="{{ old('contact.address',$quote->contact['address'] ?? '') }}" placeholder="Quartier, rue, immeuble, repère" aria-describedby="address-help"><span class="hint" id="address-help">Hors Abidjan : indiquez aussi la ville.</span></label>
