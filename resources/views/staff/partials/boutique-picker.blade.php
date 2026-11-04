{{-- Choisir la boutique adapte le formulaire : fenêtres pour les stores, articles pour l'électroménager et les meubles. Un agent ne voit que ses boutiques. --}}
@php($shops=collect(['' =>['Stores & Rideaux','Fenêtres et mesures','uni-stores'],'electromenager'=>['Électroménager','Articles, livraison, installation','uni-electro'],'meubles'=>['Meubles','Articles, livraison, montage','uni-meubles']])->filter(fn($shop,$key)=>auth()->user()->canOpenShop($key ?: 'stores')))
@if($shops->count()>1)
<section class="shop-picker" id="boutique" aria-labelledby="s-shop">
<h2 id="s-shop">Boutique</h2>
<nav class="tile-group" aria-label="Boutique">
@foreach($shops as $key=>$shop)
<a class="tile plain shop-tile" href="{{ route('staff.quotes.create',$key ? ['boutique'=>$key] : []) }}" @if(($active ?? '')===$key) aria-current="true" @endif>
<span><strong class="uni {{ $shop[2] }}">{{ $shop[0] }}</strong><small>{{ $shop[1] }}</small></span>
</a>
@endforeach
</nav>
</section>
@endif
