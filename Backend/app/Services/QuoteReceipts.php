<?php
namespace App\Services;
use App\Models\{Quote,QuotePayment};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class QuoteReceipts
{
    public static function validate(Request $r): array
    {
        $data=$r->validate(['receipt_amount'=>'nullable|integer|min:0|max:1000000000000','receipt_date'=>'nullable|date_format:Y-m-d|before_or_equal:today','receipt_method'=>['nullable',Rule::in(array_keys(QuotePayment::METHODS))],'receipt_reference'=>'nullable|string|max:255','receipt_token'=>'required|uuid','receipt_confirmed'=>'nullable|boolean']);
        if (($data['receipt_amount'] ?? 0)>0 && (empty($data['receipt_date']) || empty($data['receipt_method']) || !$r->boolean('receipt_confirmed'))) throw ValidationException::withMessages(['receipt_amount'=>'Indiquez la date et le moyen de paiement, puis confirmez que ce montant a bien été reçu.']);
        return $data;
    }
    public static function record(Quote $quote,array $data,int $userId): void
    {
        if (empty($data['receipt_amount'])) return;
        $existing=QuotePayment::where('token',$data['receipt_token'])->first();
        if ($existing) { abort_unless($existing->quote_id===$quote->id,409); return; }
        $total=Quotes\QuoteFinancials::forQuote($quote)['total'];
        if ($quote->receivedAmount()+$data['receipt_amount']>$total) throw ValidationException::withMessages(['receipt_amount'=>'Les montants reçus ne peuvent pas dépasser le total du devis.']);
        $receipt=$quote->payments()->create(['recorded_by'=>$userId,'token'=>$data['receipt_token'],'amount'=>$data['receipt_amount'],'received_on'=>$data['receipt_date'],'method'=>$data['receipt_method'],'reference'=>$data['receipt_reference'] ?? null]);
        Audit::record('paiement_devis_enregistre',$receipt);
    }
}
