
@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())<div class="notice errors" role="alert">
<strong>Vérifiez les informations suivantes :</strong>
<ul>
@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul>
</div>
@endif
