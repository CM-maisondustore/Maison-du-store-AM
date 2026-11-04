<?php
namespace App\Services;

use App\Models\{Appointment,CommercialMessage,Quote,User};

/** Interface data only: labels, menus and counters. Access control stays in routes and controllers. */
final class StaffInterface
{
    public const STATUSES=[
        'pending'=>['À traiter','warn','!'],'draft'=>['Brouillon','neutral','○'],'approved'=>['Validé','info','◆'],'accepted'=>['Accepté','ok','✓'],
        'rejected'=>['Refusé','error','×'],'cancelled'=>['Annulé','neutral','–'],'confirmed'=>['Confirmé','ok','✓'],'completed'=>['Terminé','ok','✓'],
        'planned'=>['Planifié','info','○'],'in_progress'=>['En cours','progress','◐'],'issued'=>['Émise','info','◆'],'paid'=>['Payée','ok','✓'],
        'sent'=>['Envoyé','ok','✓'],'sending'=>['Envoi en cours','info','◐'],'uncertain'=>['Envoi à vérifier','warn','!'],'received'=>['Reçu','info','●'],'shared'=>['Partagé manuellement','ok','✓'],
    ];
    public const UNIVERSES=['stores_rideaux'=>'Stores & Rideaux','electromenager'=>'Électroménager','meubles_bureau'=>'Meubles Bureau','meubles_domicile'=>'Meubles Domicile'];
    public const JOB_KINDS=['devis_sur_place'=>'Devis sur place','installation'=>'Installation','sav'=>'Service après-vente'];
    public const SOURCES=['site'=>'Site','interne'=>'Espace interne','sur_place'=>'Sur place'];
    public const QUOTE_TYPES=['store'=>'Store','rideau'=>'Rideau','electromenager'=>'Électroménager','meubles'=>'Meubles'];
    /** Communes du district d'Abidjan proposées à l'étape 1, puis « Hors Abidjan ». La valeur est enregistrée dans contact[city]. */
    public const COMMUNES=['Abobo','Adjamé','Anyama','Attécoubé','Bingerville','Cocody','Koumassi','Marcory','Plateau','Port-Bouët','Songon','Treichville','Yopougon'];
    public const OUTSIDE_ABIDJAN='Hors Abidjan';

    public static function status(?string $status): array
    {
        [$label,$tone,$glyph]=self::STATUSES[$status] ?? [$status,'neutral','·'];
        return compact('label','tone','glyph');
    }
    public static function initials(?string $name): string
    {
        $words=preg_split('/\s+/u',trim((string)$name),-1,PREG_SPLIT_NO_EMPTY) ?: [];
        $letters=array_map(fn($w)=>mb_substr($w,0,1),array_slice($words,0,2));
        return mb_strtoupper(implode('',$letters)) ?: '?';
    }
    public static function ref(?string $reference): string { return strlen((string)$reference)===36 ? substr($reference,0,8) : (string)$reference; }
    public static function date(?string $date,string $empty='À préciser'): string
    {
        if (!$date) return $empty;
        try { return \Illuminate\Support\Carbon::parse($date)->format('d/m/Y'); } catch (\Throwable) { return $date; }
    }
    public static function money(?int $value): string { return number_format((int)$value,0,',',' ').' FCFA'; }
    public static function quoteClient(Quote $quote): string { return trim(($quote->contact['firstName'] ?? '').' '.($quote->contact['lastName'] ?? '')) ?: 'Client non renseigné'; }
    public static function universeOf(array $details): string
    {
        if (isset(self::UNIVERSES[$details['universe'] ?? ''])) return $details['universe'];
        return 'stores_rideaux';
    }
    public static function universeClass(string $universe): string
    {
        return match(true) { $universe==='electromenager'=>'uni-electro', str_starts_with($universe,'meubles')=>'uni-meubles', default=>'uni-stores' };
    }
    public static function isArticleQuote(?Quote $quote): bool { return in_array($quote?->type,['electromenager','meubles'],true); }

    /** Présentation d'une notification : l'icône et la teinte suivent la rubrique visée, comme la couleur de boutique sur les bulles du site. */
    public static function notification(\Illuminate\Notifications\DatabaseNotification $notification): array
    {
        $url=(string)($notification->data['url'] ?? '');
        $internal=$url!=='' && ((str_starts_with($url,'/') && !str_starts_with($url,'//')) || parse_url($url,PHP_URL_HOST)===parse_url(url('/'),PHP_URL_HOST));
        $path=$internal ? (string)parse_url($url,PHP_URL_PATH) : '';
        [$icon,$tone]=match(true) {
            str_contains($path,'/admin/devis')=>['devis','gold'],
            str_contains($path,'/admin/rendez-vous')=>['rdv','blue'],
            str_contains($path,'/admin/prestations')=>['prestations','green'],
            str_contains($path,'/admin/messages')=>['messagerie','clay'],
            str_contains($path,'/admin/factures')=>['factures','green'],
            default=>['bell','neutral'],
        };
        return ['id'=>$notification->id,'title'=>$notification->data['title'] ?? 'Notification','message'=>$notification->data['message'] ?? '','url'=>$internal ? $url : null,'icon'=>$icon,'tone'=>$tone,'unread'=>$notification->read_at===null,'time'=>$notification->created_at?->format('d/m/Y H:i'),'ago'=>$notification->created_at?->locale('fr')->diffForHumans()];
    }

    public static function navigation(User $user): array
    {
        $is=fn(string ...$patterns)=>request()->routeIs(...array_map(fn($p)=>'staff.'.$p,$patterns));
        $item=fn(string $icon,string $label,string $route,bool $current,int $count=0,string $countLabel='')=>['icon'=>$icon,'label'=>$label,'href'=>route('staff.'.$route),'current'=>$current,'count'=>$count,'countLabel'=>$countLabel];
        $notifications=$user->unreadNotifications()->count();
        if (!$user->isAdmin()) {
            $today=$item('today','Aujourd’hui','dashboard',$is('dashboard'));
            $jobs=$item('prestations','Mes prestations','interventions.index',$is('interventions.*'));
            $appointments=$item('rdv','Mes rendez-vous','appointments.index',$is('appointments.*'));
            $quotes=$item('devis','Mes devis','quotes.index',$is('quotes.*'));
            $profile=$item('equipe','Profil','profile',$is('profile','settings','password*'));
            return ['notifications'=>$notifications,'more'=>[],
                'sections'=>[['title'=>'','items'=>[$today]],['title'=>'Mon activité','items'=>[$jobs,$appointments,$quotes]]],
                'tabs'=>[[...$today],[...$jobs,'label'=>'Prestations'],[...$quotes,'label'=>'Devis'],[...$appointments,'label'=>'RDV'],$profile]];
        }
        $quoteCount=Quote::whereIn('status',['pending','draft'])->count();
        $appointmentCount=Appointment::where('status','pending')->count();
        $messageCount=CommercialMessage::where('direction','incoming')->whereNull('read_at')->count();
        $overview=$item('overview','Vue d’ensemble','dashboard',$is('dashboard'));
        $quotes=$item('devis','Devis','quotes.index',$is('quotes.*'),$quoteCount,'devis à traiter');
        $materials=$item('materiaux','Matériaux et finitions','quote-options.index',$is('quote-options.*'));
        $appointments=$item('rdv','Rendez-vous','appointments.index',$is('appointments.*'),$appointmentCount,'rendez-vous à confirmer');
        $jobs=$item('prestations','Prestations','interventions.index',$is('interventions.*'));
        $clients=$item('clients','Clients et contacts','contacts.index',$is('contacts.*'));
        $invoices=$item('factures','Factures','invoices.index',$is('invoices.*'));
        $messages=$item('messagerie','Messagerie','messages.index',$is('messages.*'),$messageCount,'messages non lus');
        $catalogue=$item('catalogue','Catalogue','products.index',$is('products.*'));
        $images=$item('images','Images et portfolio','contents.index',$is('contents.*'));
        $documents=$item('documents','Documents et automatisations','communications.index',$is('communications.*'));
        $layouts=$item('layout','Mise en page','layouts.edit',$is('layouts.*'));
        $visitors=$item('visiteurs','Visiteurs','visitors.index',$is('visitors.*'));
        $team=$item('equipe','Équipe','users.index',$is('users.*'));
        // Le manager garde le catalogue ; les réglages du site restent à l'admin principal.
        $site=$user->isOwner() ? [$catalogue,$images,$documents,$layouts,$visitors,$team] : [$catalogue];
        return ['notifications'=>$notifications,
            'sections'=>[['title'=>'','items'=>[$overview]],
                ['title'=>'Activité','items'=>[$quotes,$materials,$appointments,$jobs]],
                ['title'=>'Gestion','items'=>[$clients,$invoices,$messages]],
                ['title'=>'Site et réglages','items'=>$site]],
            'tabs'=>[[...$overview,'icon'=>'home','label'=>'Accueil'],[...$quotes],[...$appointments,'label'=>'Planning'],[...$clients,'label'=>'Clients','count'=>0]],
            'more'=>[['title'=>'Activité','items'=>[$jobs,$materials]],['title'=>'Gestion','items'=>[$invoices,$messages]],['title'=>'Site et réglages','items'=>$site]]];
    }
}
