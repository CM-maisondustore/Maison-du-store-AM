<?php
namespace App\Services;
use App\Models\{CommunicationTemplate, Quote, Invoice, Appointment};
use Illuminate\Validation\ValidationException;
class CommunicationTemplates
{
    public const LABELS = ['quote_received'=>'Demande de devis reçue','quote'=>'Envoi du devis','invoice'=>'Envoi de facture','appointment'=>'Confirmation de rendez-vous','reminder'=>'Rappel de rendez-vous','commercial'=>'Échange commercial','promotion'=>'Offre et promotion'];
    public const VARIABLES = ['civilite','prenom','nom','reference','montant','acompte','solde','remise','total_avant_remise','conditions_paiement','date','heure','adresse','entreprise','statut',
        'site','lien_devis','lien_realisations','lien_boutique','lien_rendez_vous'];
    /** Liens vers le site insérés dans les messages ; la provenance (utm) apparaît dans Visiteurs. */
    public static function links(string $key, string $channel): array
    {
        $base=rtrim((string) config('app.url'),'/');
        $tag='utm_source='.($channel==='whatsapp' ? 'whatsapp' : 'email').'&utm_medium=message&utm_campaign='.$key;
        return ['site'=>preg_replace('#^https?://#','',$base),'lien_devis'=>$base.'/devis?'.$tag,'lien_realisations'=>$base.'/?'.$tag.'#realisations',
            'lien_boutique'=>$base.'/shop?'.$tag,'lien_rendez_vous'=>$base.'/?'.$tag.'#rendez-vous'];
    }
    /** Civilité en toutes lettres (« Madame », « Monsieur ») ; vide si elle n'est pas connue. */
    public static function civility(?string $value): string
    {
        return match(mb_strtolower(trim((string) $value))) {'mme','mme.','madame'=>'Madame','m','m.','mr','monsieur'=>'Monsieur',default=>''};
    }
    public static function defaults(string $key, string $channel='email'): array
    {
        return MdsTemplates::message($key,$channel);
    }
    public static function get(string $channel, string $key): CommunicationTemplate
    {
        $saved = CommunicationTemplate::where('channel',$channel)->where('key',$key)->first();
        if ($saved) return $saved;
        [$subject,$body] = self::defaults($key,$channel);
        return new CommunicationTemplate(['channel'=>$channel,'key'=>$key,'subject'=>$subject,'body'=>$body,'version'=>1,'options'=>[]]);
    }
    public static function validateText(string $text): void
    {
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/u',$text,$matches);
        foreach ($matches[1] as $key) if (!in_array(trim($key),self::VARIABLES,true)) throw ValidationException::withMessages(['body'=>'Variable inconnue : '.trim($key)]);
        $rest=preg_replace('/\{\{\s*([^{}]+?)\s*\}\}/u','',$text);
        if (str_contains($rest,'{{') || str_contains($rest,'}}')) throw ValidationException::withMessages(['body'=>'Une variable est mal fermée. Utilisez {{nom}}.']);
    }
    public static function render(string $text, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/u',fn($m)=>(string)($values[trim($m[1])] ?? ''),$text);
    }
    public static function context(Quote|Invoice|Appointment|null $record=null, ?\App\Models\Contact $contact=null): array
    {
        $c = $record instanceof Quote ? $record->contact : ($record instanceof Invoice ? $record->snapshot['customer'] : ($record?->details ?? []));
        $data = array_fill_keys(self::VARIABLES,'');
        $data['entreprise'] = config('workspace.issuer.name');
        $data['civilite'] = self::civility($c['civility'] ?? null);
        $data['prenom'] = $c['firstName'] ?? $contact?->name ?? '';
        $data['nom'] = trim(($c['firstName'] ?? '').' '.($c['lastName'] ?? '')) ?: ($contact?->name ?? '');
        $data['adresse'] = trim(($c['address'] ?? $contact?->address ?? '').' '.($c['city'] ?? ''));
        if ($record instanceof Quote || $record instanceof Invoice) {
            $lines=$record instanceof Quote ? ($record->lines ?? []) : $record->snapshot['lines'];
            $tax=$record instanceof Quote ? $record->tax_basis_points : $record->snapshot['tax_basis_points'];
            $data['reference']=$record instanceof Quote ? $record->reference : ($record->number ?? 'BROUILLON');
            $financial=$record instanceof Quote ? Quotes\QuoteFinancials::forQuote($record) : Quotes\QuoteFinancials::forSnapshot($record->snapshot);
            $data['montant']=number_format($financial['total'],0,',',' ');
            foreach (['acompte'=>'deposit','solde'=>'balance','remise'=>'discountAmount','total_avant_remise'=>'grossTotal'] as $variable=>$key) $data[$variable]=number_format($financial[$key] ?? 0,0,',',' ');
            if ($schedule=Quotes\QuoteFinancials::scheduleText($financial)) $data['conditions_paiement']='Conditions de paiement : '.$schedule;
            elseif (isset($financial['depositPercent'])) $data['conditions_paiement']='Conditions de paiement : acompte de '.$financial['depositPercent'].' % à la commande ('.$data['acompte'].' F CFA), solde à la livraison et pose ('.$data['solde'].' F CFA).';
        }
        if ($record instanceof Appointment) {
            $data['date']=\Carbon\Carbon::parse($record->date)->format('d/m/Y'); $data['heure']=$record->time;
            $data['statut']=match($record->status){'confirmed'=>'confirmé','cancelled'=>'annulé','completed'=>'terminé',default=>'en attente de confirmation'};
            if (($c['appointmentType'] ?? '')==='showroom') $data['adresse']=config('workspace.issuer.address') ?: 'Cocody — Marché Saint Jean, Abidjan';
        }
        return $data;
    }
    public static function draft(string $key, array $values, string $channel='email'): array
    {
        $t=self::get($channel,$key);
        $values=[...$values,...self::links($key,$channel)];
        return ['subject'=>mb_substr(trim(preg_replace(['/[\r\n]+/','/ {2,}/u'],' ',self::render($t->subject,$values))),0,200), 'body'=>trim(preg_replace(["/\n{3,}/",'/(?<=\S) {2,}(?=\S)/u'],["\n\n",' '],self::render($t->body,$values))), 'template_snapshot'=>['id'=>$t->id,'version'=>$t->version,'key'=>$key,'channel'=>$channel]];
    }
    public static function presentation(string $key): array
    {
        return DocumentLayout::presentation($key);
    }
    public static function issuer(array $presentation): array
    {
        $issuer=config('workspace.issuer');
        foreach (['name','address','registration','tax_note','payment_terms','email'] as $key) if (array_key_exists('issuer_'.$key,$presentation)) $issuer[$key]=$presentation['issuer_'.$key];
        return $issuer;
    }
}
