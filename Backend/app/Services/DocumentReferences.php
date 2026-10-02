<?php
namespace App\Services;

use App\Models\{Quote,DocumentIssue};
use Illuminate\Support\Facades\DB;

final class DocumentReferences
{
    public static function nextQuote(Quote $quote): string
    {
        return DB::transaction(function () use ($quote) {
            $sequence=DB::table('document_sequences')->where('name','quotes')->lockForUpdate()->first();
            if (!$sequence) throw new \RuntimeException('Document sequence is not initialized.');
            $number=(int)$sequence->next_value;
            DB::table('document_sequences')->where('name','quotes')->update(['next_value'=>$number+1]);
            // Use the document date; mixed quotes follow their main category.
            $date=$quote->quote_date ?: now()->toDateString();
            $category=$quote->type==='store' ? 'ST' : 'RV';
            return 'AM-'.$date.'-'.$category.'-'.str_pad((string)$number,3,'0',STR_PAD_LEFT);
        });
    }

    public static function invoice(Quote $quote): string { return $quote->reference.'-FAC'; }

    /** Called while DocumentIssues holds the quote lock. Archived receipts keep their snapshot number. */
    public static function receipt(Quote $quote): string
    {
        $ordinal=DocumentIssue::where('quote_id',$quote->id)->whereNotNull('quote_payment_id')->count()+1;
        return $quote->reference.'-REC-'.str_pad((string)$ordinal,2,'0',STR_PAD_LEFT);
    }
}
