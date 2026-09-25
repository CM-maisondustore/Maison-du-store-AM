<h2>Configuration des fenêtres</h2>
@foreach($quote->dimensions as $window)
<p><strong>{{ $window['label'] }}</strong> · {{ $window['width'] }} × {{ $window['height'] }} cm · Qté {{ $window['quantity'] }}<br>
{{ $window['product_name'] ?? '' }} · {{ $window['material_name'] ?? '' }} · {{ $window['finish_name'] ?? '' }} · Motorisé : {{ !empty($window['motorized']) ? 'Oui' : 'Non' }}
@if(!empty($window['notes']))<br>{{ $window['notes'] }}@endif</p>
@endforeach
<p class="muted">Modifiez les mesures et les choix dans le formulaire complet.</p>
