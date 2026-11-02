<h2>Configuration des ouvertures</h2>
@if(auth()->user()->isAdmin() && !empty($quote->configuration['calculation']['pending']))<p class="notice">Devis incomplet : {{ implode(' ; ',$quote->configuration['calculation']['pending']) }}. Ces postes ne sont pas inclus dans le total provisoire. Complétez le formulaire avant validation.</p>@endif
@foreach($quote->dimensions as $window)
<p><strong>{{ $window['label'] ?? 'Ouverture' }}</strong> · {{ $window['width'] ?? '' }} × {{ $window['height'] ?? '' }} cm @if(auth()->user()->isAdmin()) · Qté {{ $window['quantity'] ?? 1 }} @endif<br>
{{ $window['product_name'] ?? '' }} @if(auth()->user()->isAdmin()) · {{ $window['material_name'] ?? '' }} @endif · {{ $window['finish_name'] ?? '' }} · Motorisé : {{ !empty($window['motorized']) ? 'Oui' : 'Non' }}
@if(!empty($window['notes']))<br>{{ $window['notes'] }}@endif</p>
@if(auth()->user()->isAdmin())
@foreach($window['components'] ?? [] as $component)
<p>{{ $component['label'] }} · {{ $component['quantity'] ?? 'À mesurer' }} {{ \App\Models\QuoteOption::UNITS[$component['unit']] ?? '' }}</p>
@endforeach
@endif
<p>Zone de pose : {{ ($window['zone'] ?? $window['costing']['zone'] ?? '')==='other' ? 'Hors Abidjan' : 'Abidjan' }}</p>
@endforeach
<p class="muted">Modifiez les mesures et les choix dans le formulaire complet.</p>
