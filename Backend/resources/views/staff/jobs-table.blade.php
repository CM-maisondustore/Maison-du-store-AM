<div class="table-wrap">
<table class="cards">
<thead>
<tr>
<th scope="col">Date et heure</th>
<th scope="col">Type</th>
<th scope="col">Client</th>
<th scope="col">Agent</th>
<th scope="col">État</th>
</tr>
</thead>
<tbody>
@forelse($jobs as $job)<tr>
<td class="primary mono"><a class="row-link" href="{{ route('staff.interventions.show',$job) }}">{{ $job->scheduled_at->format('d/m/Y H:i') }}</a></td>
<td data-label="Type">{{ \App\Services\StaffInterface::JOB_KINDS[$job->kind] ?? $job->kind }}</td>
<td data-label="Client">{{ $job->contact->name }}</td>
<td data-label="Agent">{{ $job->agent->name }}</td>
<td class="state">@include('staff.status',['status'=>$job->status])</td>
</tr>
@empty<tr>
<td colspan="5" class="empty">Aucune prestation à afficher.</td>
</tr>
@endforelse</tbody>
</table>
</div>
