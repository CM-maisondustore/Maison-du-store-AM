@php($phoneParts=\App\Services\QuotePhone::split(old('contact.phone',$quote->contact['phone'] ?? '')))
<div class="quote-phone">
<label for="quote-phone-prefix">Pays / indicatif<input id="quote-phone-prefix" type="tel" name="contact[phoneCountryCode]" list="quote-phone-prefixes" pattern="\+[1-9][0-9]{0,2}" maxlength="4" autocomplete="tel-country-code" value="{{ old('contact.phoneCountryCode',$phoneParts['prefix']) }}" placeholder="+225"></label>
<datalist id="quote-phone-prefixes">@foreach(\App\Services\QuotePhone::PREFIXES as $prefix=>$country)<option value="{{ $prefix }}">{{ $country }}</option>@endforeach</datalist>
<label for="quote-contact-phone">Téléphone<input id="quote-contact-phone" required type="tel" name="contact[phone]" maxlength="30" autocomplete="tel-national" value="{{ $phoneParts['number'] }}" placeholder="07 12 34 56 78"></label>
<p class="muted">Choisissez un indicatif ou saisissez-le. Un numéro international complet (+…) est aussi accepté.</p>
</div>
