@if($style['show_client_signature'] || $style['show_company_signature'])<table class="signatures"><tr>
@if($style['show_client_signature'])<td><strong>{{ $kind==='receipt'?'CLIENT':'BON POUR ACCORD — CLIENT' }}</strong><p>Nom : ____________________</p><p>Date : ____________________</p><p>Signature :</p></td>@endif
@if($style['show_company_signature'])<td><strong>{{ $issuer['name'] }}</strong><p>{{ $style['signature'] ?? 'Nom / responsable : ____________________' }}</p><p>Date : ____________________</p><p>Signature / cachet :</p>@foreach(['signature_image','stamp_image'] as $asset)@if(!empty($style[$asset]))<img class="signature-image" src="{{ $style[$asset] }}" alt="Signature / cachet">@endif @endforeach</td>@endif
</tr></table>@endif
