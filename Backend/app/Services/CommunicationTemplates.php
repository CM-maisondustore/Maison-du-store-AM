<?php
namespace App\Services;
use App\Models\{CommunicationTemplate, Quote, Invoice, Appointment};
use Illuminate\Validation\ValidationException;
class CommunicationTemplates
{
    public const LABELS = ['quote_received'=>'Demande de devis reçue','quote'=>'Envoi du devis','invoice'=>'Envoi de facture','appointment'=>'Confirmation de rendez-vous','reminder'=>'Rappel de rendez-vous','commercial'=>'Échange commercial','promotion'=>'Offre et promotion'];
    public const VARIABLES = ['prenom','nom','reference','montant','acompte','date','heure','adresse','entreprise','statut'];
    public static function defaults(string $key): array
    {
        return match($key) {
            'quote_received'=>['Votre demande de devis a été reçue', "Bonjour {{prenom}},\n\nNous avons reçu votre demande {{reference}}. Notre équipe vous contactera pour préparer votre devis.\n\n{{entreprise}}"],
            'quote'=>['Votre devis {{reference}}', "Bonjour {{prenom}},\n\nVeuillez trouver votre devis de {{montant}} FCFA en pièce jointe. Nous restons disponibles pour toute précision.\n\n{{entreprise}}"],
            'invoice'=>['Votre facture {{reference}}', "Bonjour {{prenom}},\n\nVeuillez trouver votre facture de {{montant}} FCFA en pièce jointe.\n\n{{entreprise}}"],
            'appointment'=>['Votre rendez-vous du {{date}}', "Bonjour {{prenom}},\n\nVotre rendez-vous du {{date}} à {{heure}} (heure d’Abidjan) est {{statut}}.\nAdresse : {{adresse}}\n\n{{entreprise}}"],
            'reminder'=>['Rappel : rendez-vous du {{date}}', "Bonjour {{prenom}},\n\nNous vous rappelons votre rendez-vous du {{date}} à {{heure}} (heure d’Abidjan).\nAdresse : {{adresse}}\n\n{{entreprise}}"],
            'promotion'=>['Actualités Maison du Store AM', "Bonjour {{prenom}},\n\nDécouvrez notre offre : [compléter avant envoi].\nPour ne plus recevoir nos offres, répondez STOP.\n\n{{entreprise}}"],
            default=>['Votre projet Maison du Store AM', "Bonjour {{prenom}},\n\n[Votre message]\n\n{{entreprise}}"],
        };
    }
    public static function get(string $channel, string $key): CommunicationTemplate
    {
        $saved = CommunicationTemplate::where('channel',$channel)->where('key',$key)->first();
        if ($saved) return $saved;
        [$subject,$body] = self::defaults($key);
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
        $data['prenom'] = $c['firstName'] ?? $contact?->name ?? '';
        $data['nom'] = trim(($c['firstName'] ?? '').' '.($c['lastName'] ?? '')) ?: ($contact?->name ?? '');
        $data['adresse'] = trim(($c['address'] ?? $contact?->address ?? '').' '.($c['city'] ?? ''));
        if ($record instanceof Quote || $record instanceof Invoice) {
            $lines=$record instanceof Quote ? ($record->lines ?? []) : $record->snapshot['lines'];
            $tax=$record instanceof Quote ? $record->tax_basis_points : $record->snapshot['tax_basis_points'];
            $data['reference']=$record instanceof Quote ? $record->reference : ($record->number ?? 'BROUILLON');
            $data['montant']=number_format(Pricing::totals($lines,$tax ?? 0)['total'],0,',',' ');
            if ($record instanceof Quote) $data['acompte']=number_format($record->deposit_amount ?? 0,0,',',' ');
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
        return ['subject'=>mb_substr(preg_replace('/[\r\n]+/',' ',self::render($t->subject,$values)),0,200), 'body'=>self::render($t->body,$values), 'template_snapshot'=>['id'=>$t->id,'version'=>$t->version,'key'=>$key,'channel'=>$channel]];
    }
    public static function presentation(string $key): array
    {
        $t=self::get('pdf',$key);
        return [...($t->options ?? []),'introduction'=>$t->exists ? $t->body : '', 'template_version'=>$t->version];
    }
    public static function issuer(array $presentation): array
    {
        $issuer=config('workspace.issuer');
        foreach (['name','address','registration','tax_note','payment_terms'] as $key) if (array_key_exists('issuer_'.$key,$presentation)) $issuer[$key]=$presentation['issuer_'.$key];
        return $issuer;
    }
}
