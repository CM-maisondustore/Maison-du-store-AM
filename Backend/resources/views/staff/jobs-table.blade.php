<div class="table-wrap">
<table>
<thead>
<tr>
<th>Date / heure</th>
<th>Prestation</th>
<th>Client</th>
<th>Agent</th>
<th>État</th>
</tr>
</thead>
<tbody>
@forelse($jobs as $job)<tr>
<td>{{ $job->scheduled_at->format('d/m/Y H:i') }}</td>
<td>
<a href="{{ route('staff.interventions.show',$job) }}">{{ ['devis_sur_place'=>'Devis sur place','installation'=>'Installation','sav'=>'Service après-vente'][$job->kind] }}</a>
</td>
<td>{{ $job->contact->name }}</td>
<td>{{ $job->agent->name }}</td>
<td>
@include('staff.status',['status'=>$job->status])</td>
</tr>
@empty<tr>
<td colspan="5" class="empty">Aucune prestation à afficher.</td>
</tr>
@endforelse</tbody>
</table>
</div>
