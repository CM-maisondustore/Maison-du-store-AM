<div class="footer">
@if($style['show_company_details'])<p>{{ $issuer['name'] }} · {{ $issuer['address'] }}</p><p>
@if($style['issuer_phone'])Tél. {{ $style['issuer_phone'] }} · @endif
@if($style['issuer_whatsapp'])WhatsApp {{ $style['issuer_whatsapp'] }} · @endif
{{ $issuer['email'] }} · {{ $style['issuer_website'] }}</p>
<p>@if($style['issuer_rccm'])RCCM : {{ $style['issuer_rccm'] }} · @endif @if($style['issuer_ncc'])NCC : {{ $style['issuer_ncc'] }} · @endif {{ $issuer['registration'] }}</p>@endif
@if(!empty($issuer['tax_note']))<p class="prose">{{ $issuer['tax_note'] }}</p>@endif
@if(!empty($style['footer']))<p class="prose">{{ $style['footer'] }}</p>@endif
</div>
