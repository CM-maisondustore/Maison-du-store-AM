<section class="panel flush {{ $secondary ?? false ? 'is-secondary' : '' }}" aria-label="Boîte de réception">
<div class="panel-body pb-8">
<nav class="chips mb-8" aria-label="Filtrer">
<a class="chip" href="{{ route('staff.messages.index') }}" @if(!request('direction') && !request('filtre')) aria-current="true" @endif>Tous</a>
<a class="chip" href="{{ route('staff.messages.index',['direction'=>'incoming']) }}" @if(request('direction')==='incoming') aria-current="true" @endif>Reçus</a>
<a class="chip" href="{{ route('staff.messages.index',['direction'=>'outgoing']) }}" @if(request('direction')==='outgoing') aria-current="true" @endif>Brouillons &amp; envois</a>
</nav>
</div>
<ul class="rows">
@forelse($threads as $thread)
<li><a class="row {{ $thread->direction==='incoming' && !$thread->read_at ? 'unread' : '' }}" href="{{ route('staff.messages.show',$thread) }}" @if(($current ?? null)===$thread->id) aria-current="true" @endif>
<span class="grow"><strong>{{ $thread->direction==='incoming' ? $thread->sender : $thread->recipient }}</strong>
<span class="thread-sub">{{ $thread->subject ?: '(sans objet)' }}</span>
<small class="muted">{{ $thread->channel==='whatsapp' ? 'WhatsApp manuel' : 'E-mail' }}{{ $thread->contact ? ' · '.$thread->contact->name : '' }}@if($thread->direction==='incoming' && !$thread->read_at) · Non lu @endif</small></span>
<span class="end"><small class="mono muted">{{ $thread->created_at->format('d/m H:i') }}</small><br>@include('staff.status',['status'=>$thread->status])</span>
</a></li>
@empty<li class="empty">Aucun message enregistré.</li>
@endforelse
</ul>
</section>
