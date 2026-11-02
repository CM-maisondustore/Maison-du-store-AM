<header>
@if($style['show_logo'] && !empty($style['logo']))<img class="logo" src="{{ $style['logo'] }}" alt="Logo">@endif
@if($style['show_name'])<p class="brand">{{ $issuer['name'] }}</p>@endif
@if($style['show_activities'])<p class="muted">{{ $style['activities'] }}</p>@endif
@if($style['show_title'])<h1>{{ $document_title }}</h1>@endif
</header>
