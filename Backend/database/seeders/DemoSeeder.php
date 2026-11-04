<?php

namespace Database\Seeders;

use App\Models\{Appointment,CommercialMessage,Contact,Intervention,Invoice,Quote,User};
use Illuminate\Database\Seeder;
use RuntimeException;

/** Données fictives pour la recette locale. Refuse de tourner hors de l'environnement local. */
class DemoSeeder extends Seeder
{
    public const PASSWORD='Demo-Maison-2026';

    public function run(): void
    {
        if (!app()->environment('local')) throw new RuntimeException('DemoSeeder ne tourne qu’en environnement local.');
        $this->call(DatabaseSeeder::class);
        $user=fn($username,$name,$role)=>User::updateOrCreate(['username'=>$username],['name'=>$name,'email'=>$username.'@exemple.invalid','password'=>self::PASSWORD,'role'=>$role,'must_change_password'=>false,'active'=>true]);
        $admin=$user('demo-admin','Aïcha Diabaté','admin');
        $koffi=$user('demo-agent','Koffi Brou','agent');
        $yao=$user('demo-agent2','Yao Serge','agent');
        $contact=fn($name,$phone,$address)=>Contact::firstOrCreate(['name'=>$name],['kind'=>'client','phone'=>$phone,'email'=>\Illuminate\Support\Str::slug($name).'@exemple.invalid','address'=>$address]);
        $fatou=$contact('Mme Ouattara Fatou','+2250758442109','Villa 112, Angré 7e tranche, Cocody');
        $traore=$contact('Cabinet Traoré & Associés','+2250708123456','Immeuble Alpha 2000, Plateau');
        $aya=$contact('Mme Kouassi Aya','+2250742186605','Villa 24, Cité Riviera 3, Cocody');
        Contact::firstOrCreate(['name'=>'Fournisseur Textile CI'],['kind'=>'fournisseur','phone'=>'+2250700000099']);
        $quote=function(Contact $c,string $type,string $status,array $over=[]) use($admin){
            return Quote::create([...['type'=>$type,'status'=>$status,'source'=>'interne','created_by'=>$admin->id,'contact_id'=>$c->id,'contact'=>['firstName'=>'','lastName'=>$c->name,'email'=>$c->email,'phone'=>$c->phone,'address'=>$c->address],'product'=>[],'dimensions'=>[['label'=>'Salon','width'=>260,'height'=>240,'quantity'=>2]],'lines'=>[['description'=>'Store enrouleur occultant','quantity'=>2,'unit_price'=>62000],['description'=>'Pose et réglages','quantity'=>1,'unit_price'=>24000]],'tax_basis_points'=>1800,'valid_until'=>now()->addDays(30)->toDateString(),'terms_accepted_at'=>now()],...$over]);
        };
        $quote($fatou,'rideau','approved',['agent_id'=>$koffi->id,'source'=>'site']);
        $pending=$quote($traore,'store','pending',['source'=>'site']);
        $agentQuote=$quote($aya,'store','draft',['created_by'=>$koffi->id,'agent_id'=>$koffi->id,'source'=>'sur_place','lines'=>[],'configuration'=>['field_only'=>true,'pricing_review_required'=>true,'field_notes'=>'Plafond en dalle béton.','defaults'=>[]]]);
        $quote($fatou,'electromenager','draft',['dimensions'=>[],'product'=>['model'=>'Électroménager'],'lines'=>[['description'=>'Réfrigérateur 300 L, Inox','quantity'=>1,'unit_price'=>0],['description'=>'Livraison','quantity'=>1,'unit_price'=>15000]],'configuration'=>['universe'=>'electromenager','articles'=>[['name'=>'Réfrigérateur 300 L','variant'=>'Inox','quantity'=>1]],'services'=>['delivery'=>true],'floor'=>'2','has_lift'=>false,'field_notes'=>'','calculation'=>['pending'=>['Réfrigérateur 300 L, Inox']],'pricing_review_required'=>false]]);
        $accepted=$quote($aya,'store','accepted',['agent_id'=>$koffi->id]);
        Invoice::firstOrCreate(['quote_id'=>$accepted->id],['contact_id'=>$aya->id,'due_date'=>now()->addDays(30)->toDateString(),'status'=>'issued','number'=>$accepted->reference.'-FAC','issued_at'=>now()->subDays(2),'snapshot'=>['customer'=>$accepted->contact,'lines'=>$accepted->lines,'tax_basis_points'=>1800,'issuer'=>config('workspace.issuer'),'quote_reference'=>$accepted->reference,'windows'=>[],'payment_plan'=>['mode'=>null,'deposit'=>0,'received'=>0]]]);
        $job=fn($c,$agent,$kind,$when,$status='planned',$quoteId=null,$note=null)=>Intervention::create(['contact_id'=>$c->id,'agent_id'=>$agent->id,'quote_id'=>$quoteId,'kind'=>$kind,'scheduled_at'=>$when,'duration_minutes'=>90,'address'=>$c->address,'instructions'=>$note,'status'=>$status]);
        $job($aya,$koffi,'installation',today()->addHours(16),'planned',$agentQuote->id,'Code du portail 4521. Prévoir l’escabeau de 3 m.');
        $job($traore,$koffi,'devis_sur_place',today()->addDay()->addHours(10));
        $job($fatou,$yao,'sav',today()->addDays(2)->addHours(9),'planned',null,'Moteur du salon à contrôler.');
        foreach ([[0,'10:00','pending',$fatou,$koffi],[1,'14:30','confirmed',$traore,null],[2,'09:00','confirmed',$aya,$koffi]] as [$offset,$time,$status,$c,$agent]) {
            Appointment::firstOrCreate(['date'=>today()->addDays($offset)->toDateString(),'time'=>$time],['reference'=>(string)\Illuminate\Support\Str::uuid(),'status'=>$status,'contact_id'=>$c->id,'agent_id'=>$agent?->id,'details'=>['firstName'=>'','lastName'=>$c->name,'phone'=>$c->phone,'email'=>$c->email,'appointmentType'=>'Showroom']]);
        }
        CommercialMessage::create(['direction'=>'incoming','status'=>'received','sender'=>$traore->email,'recipient'=>'contact@maisondustoream.com','subject'=>'Question sur notre devis','body'=>'Bonjour, pouvez-vous confirmer le délai de pose ?','contact_id'=>$traore->id]);
        CommercialMessage::create(['recipient'=>$pending->contact['email'],'sender'=>'contact@maisondustoream.com','subject'=>'Votre devis Maison du Store AM','body'=>'Bonjour, veuillez trouver votre devis.','quote_id'=>$pending->id,'contact_id'=>$traore->id,'kind'=>'devis','user_id'=>$admin->id]);
        $this->notify($admin,[['Nouveau devis demandé','Une demande '.$pending->reference.' vient d’être reçue.',route('staff.quotes.show',$pending),5],['Nouveau rendez-vous demandé','Une demande de rendez-vous pour aujourd’hui à 10:00 vient d’être reçue.',route('staff.appointments.index'),40],['Nouveau message','Cabinet Traoré & Associés demande le délai de pose.',route('staff.messages.index'),90],['Facture à suivre','La facture de Mme Kouassi Aya reste à encaisser.',route('staff.invoices.index'),300],['Prestation terminée','Koffi Brou a clos une installation à Cocody.',route('staff.interventions.index'),1500]]);
        $this->notify($koffi,[['Nouvelle prestation affectée','Une prestation vous est affectée aujourd’hui à 16:00.',route('staff.interventions.index'),15],['Prestation mise à jour','Les informations de votre prestation ont été mises à jour.',route('staff.interventions.index'),200]]);
    }

    /** Sans file d'envoi : ces alertes fictives ne partent jamais vers un navigateur. */
    private function notify(User $user,array $rows): void
    {
        foreach ($rows as [$title,$message,$url,$minutes]) {
            $at=now()->subMinutes($minutes);
            \Illuminate\Support\Facades\DB::table('notifications')->insert(['id'=>(string)\Illuminate\Support\Str::uuid(),'type'=>\App\Notifications\StaffAlert::class,'notifiable_type'=>$user->getMorphClass(),'notifiable_id'=>$user->id,'data'=>json_encode(['title'=>$title,'message'=>$message,'url'=>$url],JSON_THROW_ON_ERROR),'created_at'=>$at,'updated_at'=>$at]);
        }
    }
}
