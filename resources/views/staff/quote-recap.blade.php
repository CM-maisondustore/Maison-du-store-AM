{{-- Récapitulatif de l'étape 4 : relit les champs du formulaire (quote-editor.js le tient à jour), n'enregistre rien. --}}
@php
 $recapName=trim(old('contact.firstName',$quote->contact['firstName'] ?? '').' '.old('contact.lastName',$quote->contact['lastName'] ?? ''));
 $recapPhone=trim(old('contact.phone',$quote->contact['phone'] ?? ''));
 $recapAgent=$agents->firstWhere('id',(int)old('agent_id',$quote->agent_id))?->name;
 $recapOpenings=collect($windows)->map(fn($w)=>trim(($w['label'] ?? 'Ouverture').' '.(!empty($w['width']) || !empty($w['height']) ? ($w['width'] ?? '?').' × '.($w['height'] ?? '?') : '').(($w['quantity'] ?? 1)>1 ? ' ×'.$w['quantity'] : '')))->implode(' · ');
@endphp
<aside class="panel wizard-side quote-recap" id="quote-recap" aria-labelledby="h-recap"><h2 id="h-recap">Récapitulatif</h2>
<dl class="detail-list">
<dt>Client</dt><dd data-recap="client">{{ implode(' · ',array_filter([$recapName,$recapPhone])) ?: 'À saisir à l’étape 1' }}</dd>
<dt>Ouvertures</dt><dd data-recap="openings">{{ $recapOpenings }}</dd>
<dt>Agent</dt><dd data-recap="agent">{{ $recapAgent ?? 'Non affecté' }}</dd>
<dt data-recap="total-label">{{ $liveTotal ? ($pendingLines ? 'Total provisoire' : 'Total définitif') : 'Total' }}</dt><dd class="recap-total" data-recap="total">{{ $liveTotal ? number_format($financial['finalTotal'],0,',',' ').' FCFA' : 'À calculer à l’étape 3' }}</dd>
</dl>
<p class="notice" data-recap="pending" data-pending="{{ json_encode(array_values($liveTotal ? $pendingLines : [])) }}" @unless($liveTotal && $pendingLines) hidden @endunless><strong data-recap="pending-count">{{ count($pendingLines) }} {{ count($pendingLines)>1 ? 'lignes' : 'ligne' }} à définir.</strong> <span data-recap="pending-list">{{ implode(' ; ',$pendingLines) }}</span>. Le devis s’enregistre en brouillon, mais il ne pourra être validé qu’une fois ces prix saisis.</p>
</aside>
