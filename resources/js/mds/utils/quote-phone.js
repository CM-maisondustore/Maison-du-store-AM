(function(MDS){'use strict';
  function normalize(phone,prefix=''){
    let value=String(phone||'').trim().replace(/[\s.\-()]/g,'');
    if(value.startsWith('00'))value='+'+value.slice(2);
    if(!value||value.startsWith('+')||!prefix)return value;
    if(['+33','+44','+233','+212'].includes(prefix)&&value.startsWith('0'))value=value.slice(1);
    return prefix+value;
  }
  function render(contact){const esc=MDS.validation.escapeHtml;
    return '<div class="form-grid__full quote-phone-row"><div class="field" data-field="phoneCountryCode"><label class="field__label" for="quote-phone-country">Pays / indicatif</label><input class="input" type="tel" id="quote-phone-country" name="phoneCountryCode" list="quote-phone-prefixes" autocomplete="tel-country-code" maxlength="4" placeholder="+225" value="'+esc(contact.phoneCountryCode??'+225')+'"><datalist id="quote-phone-prefixes">'+Object.entries(MDS.phonePrefixes||{}).map(([code,country])=>'<option value="'+esc(code)+'">'+esc(country)+'</option>').join('')+'</datalist></div><div class="field" data-field="phone"><label class="field__label" for="quote-phone">Téléphone</label><input class="input" type="tel" id="quote-phone" name="phone" autocomplete="tel-national" maxlength="30" placeholder="07 12 34 56 78" value="'+esc(contact.phone)+'"></div><p class="field__hint form-grid__full">Choisissez un indicatif ou saisissez-le. Un numéro international complet (+…) est aussi accepté.</p></div>';
  }
  MDS.quotePhone={normalize,render};
})((window.MDS=window.MDS||{}));
