<?php
namespace App\Services\Quotes;

use App\Models\Quote;

final class QuoteWorkflow
{
    public const STAGES=['in_production'=>'En fabrication','installation_scheduled'=>'Pose planifiée','installed'=>'Installé','balance_pending'=>'Solde attendu','paid'=>'Soldé'];
    public static function state(Quote $quote): string
    {
        if (in_array($quote->status,['cancelled','rejected'])) return 'cancelled';
        if ($quote->status!=='accepted') {
            $sent=\App\Models\CommercialMessage::where('quote_id',$quote->id)->whereIn('status',['sent','shared'])->exists();
            return $sent ? 'sent' : ($quote->status==='approved' ? 'approved' : 'draft');
        }
        $financial=QuoteFinancials::forQuote($quote);
        if (!$financial['productionAuthorized']) return 'deposit_pending';
        return $quote->configuration['workflow_stage'] ?? 'deposit_paid';
    }
}
