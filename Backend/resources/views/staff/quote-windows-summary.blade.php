<h2>Configuration des fenêtres</h2>
@if(auth()->user()->isAdmin() && !empty($quote->configuration['calculation']['pending']))<p class="notice">Devis incomplet : {{ implode(' ; ',$quote->configuration['calculation']['pending']) }}. Ces postes ne sont pas inclus dans le total provisoire. Complétez le formulaire avant validation.</p>@endif
@foreach($quote->dimensions as $window)
<p><strong>{{ $window['label'] ?? 'Fenêtre' }}</strong> · {{ $window['width'] ?? '' }} × {{ $window['height'] ?? '' }} cm · Qté {{ $window['quantity'] ?? 1 }}<br>
{{ $window['product_name'] ?? '' }} · {{ $window['material_name'] ?? '' }} · {{ $window['finish_name'] ?? '' }} · Motorisé : {{ !empty($window['motorized']) ? 'Oui' : 'Non' }}
@if(!empty($window['notes']))<br>{{ $window['notes'] }}@endif</p>
@foreach($window['components'] ?? [] as $component)<p>{{ $component['label'] }} · {{ $component['quantity'] ?? 'À mesurer' }} {{ \App\Models\QuoteOption::UNITS[$component['unit']] ?? '' }}</p>@endforeach
@endforeach
<p class="muted">Modifiez les mesures et les choix dans le formulaire complet.</p>
