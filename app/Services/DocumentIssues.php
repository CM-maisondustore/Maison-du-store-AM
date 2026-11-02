<?php
namespace App\Services;
use App\Models\{Quote,QuotePayment,DocumentIssue,DocumentArchive,CommercialMessage};
use Illuminate\Support\Facades\DB;

final class DocumentIssues
{
    /** Payments and visual changes do not silently create another version of an issued quote. */
    public static function fingerprint(array $data): string
    {
        $financial=$data['financial'] ?? [];
        foreach (['receivedAmount','outstanding','paymentStatus','productionAuthorized'] as $key) unset($financial[$key]);
        $content=collect($data)->only(['number','date','customer','lines','tax_basis_points','windows','due','schedule','object','rooms'])->all();
        $content['financial']=$financial;
        $content['payment_terms']=collect($data['payment_plan'] ?? [])->only(['mode','deposit'])->all();
        $canonical=function ($value) use (&$canonical) {
            if (!is_array($value)) return $value;
            if (!array_is_list($value)) ksort($value);
            return array_map($canonical,$value);
        };
        return hash('sha256',json_encode($canonical($content),JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));
    }
    public static function quote(Quote $quote, Documents $documents): DocumentIssue
    {
        return DB::transaction(function () use ($quote,$documents) {
            $quote=Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($quote->status,['approved','accepted']),409,'Validez le devis avant émission.');
            $data=$documents->quoteData($quote); $fingerprint=self::fingerprint($data);
            $existing=DocumentIssue::where('quote_id',$quote->id)->whereNull('quote_payment_id')->where('fingerprint',$fingerprint)->first();
            if ($existing) return $existing;
            // Reuse actual sent bytes whenever the commercial contents match. Never overwrite an archive.
            foreach (CommercialMessage::where('quote_id',$quote->id)->whereNull('invoice_id')->whereNotNull('document_snapshot')->latest('id')->lazy(1) as $message) {
                if (self::fingerprint($message->document_snapshot)===$fingerprint && $message->archive) {
                    return DocumentIssue::create(['quote_id'=>$quote->id,'fingerprint'=>$fingerprint,'snapshot'=>$message->document_snapshot,'document_archive_id'=>$message->archive->id]);
                }
            }
            $archive=DocumentArchive::capture($documents->render($data),[]);
            return DocumentIssue::create(['quote_id'=>$quote->id,'fingerprint'=>$fingerprint,'snapshot'=>$data,'document_archive_id'=>$archive->id]);
        });
    }
    public static function receipt(Quote $quote, QuotePayment $payment, Documents $documents): DocumentIssue
    {
        return DB::transaction(function () use ($quote,$payment,$documents) {
            $quote=Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $payment=QuotePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($payment->quote_id===$quote->id,404);
            $existing=DocumentIssue::where('quote_payment_id',$payment->id)->first();
            if ($existing) return $existing;
            abort_if($payment->voided_at,409,'Un paiement annulé ne peut pas donner lieu à un nouveau reçu.');
            $data=$documents->receiptData($quote,$payment);
            $archive=DocumentArchive::capture($documents->render($data),[]);
            $issue=DocumentIssue::create(['quote_id'=>$quote->id,'quote_payment_id'=>$payment->id,'fingerprint'=>hash('sha256','receipt:'.$payment->id),'snapshot'=>$data,'document_archive_id'=>$archive->id]);
            Audit::record('recu_emis',$payment); return $issue;
        });
    }
}
