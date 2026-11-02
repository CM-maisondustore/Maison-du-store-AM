@if(session('success'))<div class="notice success bubble-flash" role="status" data-flash-bubble><span class="bubble-c tone-green" aria-hidden="true">@include('staff.partials.icon',['n'=>'check','s'=>20])</span><span class="bubble-l">{{ session('success') }}</span></div>
@endif
@if($errors->has('edit_version'))<div class="notice errors" role="alert"><strong>Modification concurrente détectée.</strong> {{ $errors->first('edit_version') }} Vos changements ne sont pas enregistrés.</div>
@endif
@if($errors->any())<div class="notice errors" role="alert">
<strong>Vérifiez les informations suivantes :</strong>
<ul>
@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul>
</div>
@endif
