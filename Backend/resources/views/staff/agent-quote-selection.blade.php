@php($project=$selection['project_type'] ?? (($selection['category'] ?? 'store')==='store' ? 'store' : (!empty($selection['curtain_id']) && !empty($selection['sheer_id']) ? 'rideau_voilage' : (!empty($selection['sheer_id']) ? 'voilage' : 'rideau'))))
<div class="grid" data-agent-selection>
<label>Type de projet<select data-agent-choice="project_type" name="{{ $prefix }}[project_type]">@foreach(\App\Services\AgentQuoteData::TYPES as $value=>$label)<option value="{{ $value }}" @selected($project===$value)>{{ $label }}</option>@endforeach</select></label>
{{-- Tissus et voilage détaillent le type de projet, qui reste le seul champ enregistré : agent-quote.js les tient alignés. --}}
@foreach(['curtain'=>['Tissus (rideaux)',['rideau','rideau_voilage']],'sheer'=>['Voilage',['voilage','rideau_voilage']]] as $part=>[$label,$types])
<label data-agent-fabric @if($project==='store') hidden @endif>{{ $label }}<select data-agent-part="{{ $part }}"><option value="1" @selected(in_array($project,$types,true))>Oui</option><option value="0" @selected(!in_array($project,$types,true))>Non</option></select></label>
@endforeach
<label>Motorisation<select data-agent-choice="motorized" name="{{ $prefix }}[motorized]"><option value="0" @selected(empty($selection['motorized']))>Non</option><option value="1" @selected(!empty($selection['motorized']))>Oui</option></select></label>
<label>Finition<select required data-agent-choice="finish_id" data-value="{{ $selection['finish_id'] ?? '' }}" name="{{ $prefix }}[finish_id]"></select></label>
<label>Zone de pose<select data-agent-choice="zone" name="{{ $prefix }}[zone]"><option value="abidjan" @selected(($selection['zone'] ?? $selection['costing']['zone'] ?? 'abidjan')==='abidjan')>Abidjan</option><option value="other" @selected(($selection['zone'] ?? $selection['costing']['zone'] ?? '')==='other')>Hors Abidjan</option></select></label>
</div>
