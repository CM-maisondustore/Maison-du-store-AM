@foreach($windows ?? [] as $window)<p><strong>{{ $window['label'] }}</strong> · {{ $window['width'] }} × {{ $window['height'] }} cm · Qté {{ $window['quantity'] }}</p>@endforeach
@php($documentLines=array_map(fn($line)=>['label'=>\App\Services\Quotes\CatalogueCalculator::clientLabel($line['description']),'displayQuantity'=>\App\Services\Quotes\QuoteFinancials::quantity($line['quantity']),'unit'=>'','unitPrice'=>$line['unit_price'],'displayAmount'=>$line['amount']],$lines))
@include('documents.partials.table')
