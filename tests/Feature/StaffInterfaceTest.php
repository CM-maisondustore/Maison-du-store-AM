<?php
namespace Tests\Feature;

use App\Models\{Appointment,Contact,Intervention,Invoice,Product,Quote,QuoteOption,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role='admin'): User
    {
        return User::create(['username'=>Str::random(10),'name'=>'Equipe Test','email'=>Str::random(10).'@example.com','password'=>'TemporaryPass123!','role'=>$role,'must_change_password'=>false]);
    }
    private function contact(string $name='Client Test'): Contact { return Contact::create(['name'=>$name,'email'=>Str::random(8).'@example.com','phone'=>'+2250700000000']); }
    private function article(array $override=[]): array
    {
        return [...['submission_token'=>(string)Str::uuid(),'edit_version'=>0,'universe'=>'electromenager',
            'contact'=>['firstName'=>'Awa','lastName'=>'Client','phone'=>'+2250700000000','address'=>'Cocody','email'=>'','city'=>'Abidjan'],
            'articles'=>[['name'=>'Réfrigérateur 300 L','variant'=>'Inox','quantity'=>2]],
            'services'=>['delivery'=>['enabled'=>1]],'floor'=>'2','has_lift'=>0],...$override];
    }
    private function adminArticle(array $override=[]): array
    {
        return [...$this->article(),'tax_rate'=>18,'payment_mode'=>'advance','deposit_amount'=>0,'receipt_token'=>(string)Str::uuid(),'quote_date'=>now()->format('Y-m-d'),'valid_until'=>now()->addDays(30)->format('Y-m-d'),...$override];
    }

    public function test_new_screens_require_a_session_and_are_never_cacheable(): void
    {
        foreach (['/admin/recherche','/admin/profil','/admin/parametres'] as $url) $this->get($url)->assertRedirect('/admin/connexion');
        $this->actingAs($this->user());
        foreach (['/admin/recherche?q=test','/admin/profil','/admin/parametres'] as $url) {
            $response=$this->get($url)->assertOk()->assertHeader('X-Robots-Tag','noindex, nofollow');
            $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        }
    }

    private function alert(User $user,string $title,string $url,?string $readAt=null): string
    {
        $id=(string)Str::uuid();
        \Illuminate\Support\Facades\DB::table('notifications')->insert(['id'=>$id,'type'=>\App\Notifications\StaffAlert::class,'notifiable_type'=>$user->getMorphClass(),'notifiable_id'=>$user->id,'data'=>json_encode(['title'=>$title,'message'=>'Détail','url'=>$url]),'read_at'=>$readAt,'created_at'=>now(),'updated_at'=>now()]);
        return $id;
    }

    public function test_recent_notifications_are_private_uncached_and_do_not_mark_as_read(): void
    {
        $this->get('/admin/notifications/recentes')->assertRedirect('/admin/connexion');
        $this->getJson('/admin/notifications/recentes')->assertUnauthorized();
        $me=$this->user('agent'); $other=$this->user('agent');
        $mine=$this->alert($me,'Pour moi',url('/admin/prestations/1')); $this->alert($other,'Pour un collègue',url('/admin/prestations/2'));
        $response=$this->actingAs($me)->getJson('/admin/notifications/recentes')->assertOk()->assertJsonPath('count',1)->assertJsonCount(1,'items')->assertJsonPath('items.0.id',$mine)->assertJsonPath('items.0.icon','prestations')->assertJsonPath('items.0.unread',true)->assertDontSee('Pour un collègue');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertSame(1,$me->unreadNotifications()->count());
    }

    public function test_notification_links_stay_inside_the_application(): void
    {
        $me=$this->user();
        $this->alert($me,'Externe','https://exemple.invalid/piege'); $this->alert($me,'Protocole','javascript:alert(1)'); $this->alert($me,'Double barre','//exemple.invalid/piege');
        $items=$this->actingAs($me)->getJson('/admin/notifications/recentes')->assertOk()->json('items');
        foreach ($items as $item) $this->assertNull($item['url'],$item['title']);
    }

    public function test_notifications_page_shows_bubbles_then_marks_as_read(): void
    {
        $this->actingAs($me=$this->user());
        $this->get('/admin/notifications')->assertOk()->assertSee('Aucune notification pour le moment.')->assertDontSee('data-bubbles',false);
        $this->alert($me,'Devis reçu',url('/admin/devis/1'));
        $this->get('/admin/notifications')->assertOk()->assertSee('data-bubbles',false)->assertSee('Devis reçu')->assertSee('tone-gold',false)->assertSee('Nouveau');
        $this->assertSame(0,$me->unreadNotifications()->count());
        $this->get('/admin/notifications')->assertOk()->assertDontSee('notif-new',false);
    }

    public function test_every_staff_page_has_the_bubble_tray_and_no_tracking(): void
    {
        $this->actingAs($this->user());
        $this->get('/admin')->assertOk()->assertSee('data-bubble-tray',false)->assertSee(route('staff.notifications.recent'),false)->assertDontSee('fbq(',false);
    }

    public function test_mobile_title_is_not_escaped_twice(): void
    {
        $this->actingAs($this->user());
        $this->get('/admin/communications')->assertOk()->assertDontSee('&amp;amp;',false);
    }

    public function test_error_pages_use_the_house_style_without_leaking_details(): void
    {
        $this->actingAs($this->user('agent'));
        $this->get('/admin/factures')->assertForbidden()->assertSee('Accès réservé')->assertSee('Revenir à l’espace pro')->assertDontSee('FCFA');
        $this->get('/admin/page-absente')->assertNotFound();
    }

    public function test_navigation_follows_the_role_and_server_still_refuses_hidden_modules(): void
    {
        $agent=$this->user('agent'); $this->actingAs($agent);
        $html=$this->get('/admin')->assertOk()->assertSee('Aujourd’hui')->getContent();
        foreach (['/admin/factures','/admin/contacts','/admin/messages','/admin/equipe','/admin/products','/admin/communications','/admin/mise-en-page','/admin/visiteurs','/admin/options-devis','/admin/contents'] as $url) {
            $this->assertStringNotContainsString('href="'.url($url).'"',$html);
            $this->get($url)->assertForbidden();
        }
        auth()->logout(); session()->flush();
        $this->actingAs($this->user())->get('/admin')->assertOk()->assertSee('Vue d’ensemble')->assertSee('href="'.url('/admin/factures').'"',false);
    }

    public function test_agent_pages_never_contain_prices_payments_or_financial_fields(): void
    {
        $agent=$this->user('agent'); $contact=$this->contact();
        $quote=Quote::create(['type'=>'store','created_by'=>$agent->id,'agent_id'=>$agent->id,'source'=>'interne','status'=>'draft','contact_id'=>$contact->id,'contact'=>['firstName'=>'Awa','lastName'=>'Client','email'=>$contact->email,'phone'=>'+2250700000000','address'=>'Cocody'],'product'=>[],'dimensions'=>[['label'=>'Salon','width'=>120,'height'=>200,'quantity'=>1,'product_name'=>'Stores']],'configuration'=>['field_notes'=>'Note','calculation'=>['financial'=>['total'=>987654]]],'lines'=>[['description'=>'Secret','quantity'=>1,'unit_price'=>987654]],'tax_basis_points'=>1800,'terms_accepted_at'=>now()]);
        $job=Intervention::create(['contact_id'=>$contact->id,'quote_id'=>$quote->id,'agent_id'=>$agent->id,'kind'=>'installation','scheduled_at'=>now()->addHour(),'duration_minutes'=>60,'address'=>'Cocody']);
        $this->actingAs($agent);
        foreach (['/admin','/admin/devis','/admin/devis/'.$quote->id,'/admin/devis/'.$quote->id.'/formulaire','/admin/devis/nouveau','/admin/devis/nouveau?boutique=meubles','/admin/prestations','/admin/prestations/'.$job->id,'/admin/rendez-vous','/admin/recherche?q=Client','/admin/profil','/admin/parametres'] as $url) {
            $html=$this->get($url)->assertOk()->getContent();
            foreach (['FCFA','F CFA','987654','987 654','unit_price','Reste à recevoir','Prix unitaire','Secret'] as $forbidden) $this->assertStringNotContainsString($forbidden,$html,$url.' expose '.$forbidden);
        }
    }

    public function test_search_is_limited_to_what_the_role_may_read(): void
    {
        $agent=$this->user('agent'); $other=$this->user('agent'); $contact=$this->contact('Cabinet Recherche');
        $own=Quote::create(['type'=>'store','created_by'=>$agent->id,'status'=>'draft','contact_id'=>$contact->id,'contact'=>['firstName'=>'','lastName'=>'Cabinet Recherche'],'product'=>[],'dimensions'=>[],'terms_accepted_at'=>now()]);
        $foreign=Quote::create(['type'=>'store','created_by'=>$other->id,'status'=>'draft','contact_id'=>$contact->id,'contact'=>['firstName'=>'','lastName'=>'Cabinet Recherche'],'product'=>[],'dimensions'=>[],'terms_accepted_at'=>now()]);
        $invoice=Invoice::create(['quote_id'=>$foreign->id,'contact_id'=>$contact->id,'due_date'=>now()->addDay(),'snapshot'=>['lines'=>[],'customer'=>[]]]);
        $this->actingAs($agent)->get('/admin/recherche?q=Recherche')->assertOk()->assertSee(route('staff.quotes.show',$own))->assertDontSee(route('staff.quotes.show',$foreign))->assertDontSee(route('staff.contacts.show',$contact))->assertDontSee(route('staff.invoices.show',$invoice));
        auth()->logout(); session()->flush();
        $this->actingAs($this->user())->get('/admin/recherche?q=Recherche')->assertOk()->assertSee(route('staff.quotes.show',$foreign))->assertSee(route('staff.contacts.show',$contact))->assertSee(route('staff.invoices.show',$invoice));
    }

    public function test_quote_filters_cannot_widen_an_agent_view(): void
    {
        $agent=$this->user('agent'); $other=$this->user('agent'); $contact=$this->contact();
        $foreign=Quote::create(['type'=>'store','created_by'=>$other->id,'agent_id'=>$other->id,'status'=>'draft','contact_id'=>$contact->id,'contact'=>['lastName'=>'Secret'],'product'=>[],'dimensions'=>[],'terms_accepted_at'=>now()]);
        $this->actingAs($agent)->get('/admin/devis?agent='.$other->id)->assertOk()->assertDontSee(route('staff.quotes.show',$foreign));
    }

    public function test_quote_list_filters_by_state_type_and_search(): void
    {
        $contact=$this->contact();
        $a=Quote::create(['type'=>'store','status'=>'pending','contact_id'=>$contact->id,'contact'=>['lastName'=>'Alpha'],'product'=>[],'dimensions'=>[],'terms_accepted_at'=>now()]);
        $b=Quote::create(['type'=>'rideau','status'=>'draft','contact_id'=>$contact->id,'contact'=>['lastName'=>'Beta'],'product'=>[],'dimensions'=>[],'terms_accepted_at'=>now()]);
        $this->actingAs($this->user());
        $this->get('/admin/devis?status=pending')->assertSee(route('staff.quotes.show',$a))->assertDontSee(route('staff.quotes.show',$b));
        $this->get('/admin/devis?type=rideau')->assertSee(route('staff.quotes.show',$b))->assertDontSee(route('staff.quotes.show',$a));
        $this->get('/admin/devis?q=Alpha')->assertSee(route('staff.quotes.show',$a))->assertDontSee(route('staff.quotes.show',$b));
        $this->get('/admin/devis?q=alpha')->assertSee(route('staff.quotes.show',$a));
        $this->get('/admin/recherche?q=alpha')->assertSee(route('staff.quotes.show',$a));
    }

    public function test_blank_article_price_means_to_define_and_blocks_validation(): void
    {
        $this->actingAs($this->user());
        $this->get('/admin/devis/nouveau?boutique=electromenager')->assertOk()->assertSee('Articles')->assertSee('Installation par appareil')->assertDontSee('Choix communs');
        $this->post('/admin/devis',$this->adminArticle())->assertRedirect()->assertSessionHasNoErrors();
        $quote=Quote::firstOrFail();
        $this->assertSame('electromenager',$quote->type);
        $this->assertStringContainsString('-EL-',$quote->reference);
        $this->assertCount(2,$quote->configuration['calculation']['pending']);
        $this->get('/admin/devis/'.$quote->id)->assertOk()->assertSee('Devis incomplet')->assertSee('À définir')->assertSee('Total TTC provisoire')->assertSee('Livraison (2e étage sans ascenseur)')->assertDontSee('Valider le devis');
        $state=['edit_version'=>$quote->edit_version,'status'=>'approved','tax_rate'=>18,'valid_until'=>$quote->valid_until,'lines'=>$quote->lines];
        $this->put('/admin/devis/'.$quote->id,$state)->assertSessionHasErrors('status');
        $this->assertSame('draft',$quote->fresh()->status);
    }

    public function test_priced_article_quote_follows_the_normal_workflow_with_unchanged_totals(): void
    {
        config(['workspace.issuer.address'=>'Abidjan','workspace.issuer.registration'=>'Registre test','workspace.issuer.tax_note'=>'Mention test','workspace.issuer.payment_terms'=>'Paiement']);
        $this->actingAs($this->user());
        $data=$this->adminArticle(['universe'=>'meubles','articles'=>[['name'=>'Canapé','variant'=>'','quantity'=>2,'unit_price'=>100000]],'services'=>['assembly'=>['enabled'=>1,'unit_price'=>20000]],'floor'=>'rdc']);
        $this->post('/admin/devis',$data)->assertRedirect()->assertSessionHasNoErrors();
        $quote=Quote::firstOrFail();
        $this->assertStringContainsString('-MB-',$quote->reference);
        $this->assertSame(['subtotal'=>220000,'tax'=>39600,'total'=>259600],\App\Services\Pricing::totals($quote->lines,$quote->tax_basis_points));
        $this->get('/admin/devis/'.$quote->id)->assertOk()->assertSee('259 600')->assertSee('Valider le devis');
        $this->get('/admin/devis/'.$quote->id.'/formulaire')->assertOk()->assertSee('value="100000"',false)->assertSee('value="20000"',false);
        $this->get('/admin/devis/'.$quote->id.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        $state=['edit_version'=>$quote->edit_version,'status'=>'approved','tax_rate'=>18,'valid_until'=>$quote->valid_until,'lines'=>$quote->lines];
        $this->put('/admin/devis/'.$quote->id,$state)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved',$quote->fresh()->status);
        $this->get('/admin/devis/'.$quote->id)->assertOk()->assertSee('Enregistrer l’acceptation');
        $this->put('/admin/devis/'.$quote->id,[...$state,'edit_version'=>$quote->fresh()->edit_version,'status'=>'accepted','internal_notes'=>'Accord écrit'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/admin/devis/'.$quote->id.'/facture',['due_date'=>now()->addDays(30)->format('Y-m-d')])->assertRedirect();
        $invoice=Invoice::firstOrFail();
        $this->post('/admin/factures/'.$invoice->id.'/emettre')->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/factures/'.$invoice->id)->assertOk()->assertSee('259 600');
        $this->get('/admin/factures/'.$invoice->id.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
    }

    public function test_article_form_refuses_a_stale_save(): void
    {
        $this->actingAs($this->user());
        $this->post('/admin/devis',$this->adminArticle())->assertRedirect();
        $quote=Quote::firstOrFail();
        $this->put('/admin/devis/'.$quote->id.'/formulaire',$this->adminArticle(['edit_version'=>$quote->edit_version+5]))->assertSessionHasErrors('edit_version');
    }

    public function test_agent_article_quote_has_no_price_and_keeps_office_prices_on_update(): void
    {
        $agent=$this->user('agent'); $this->actingAs($agent);
        $this->post('/admin/devis',$this->article(['articles'=>[['name'=>'Four','quantity'=>1,'unit_price'=>5000]]]))->assertSessionHasErrors('articles.0.unit_price');
        $this->post('/admin/devis',$this->article(['tax_rate'=>18]))->assertSessionHasErrors('tax_rate');
        $this->post('/admin/devis',$this->article())->assertRedirect()->assertSessionHasNoErrors();
        $quote=Quote::firstOrFail();
        $this->assertTrue($quote->configuration['pricing_review_required']);
        $this->assertSame($agent->id,$quote->agent_id);
        $this->get('/admin/devis/'.$quote->id)->assertOk()->assertSee('Réfrigérateur 300 L')->assertDontSee('FCFA');
        $this->get('/admin/devis/'.$quote->id.'/pdf')->assertForbidden();
        $lines=$quote->lines; $lines[0]['unit_price']=450000;
        $configuration=$quote->configuration; $configuration['calculation']['pending']=[$lines[1]['description']];
        $quote->update(['lines'=>$lines,'configuration'=>$configuration]);
        $this->put('/admin/devis/'.$quote->id.'/formulaire',$this->article(['edit_version'=>$quote->edit_version,'submission_token'=>$quote->submission_token,'articles'=>[['name'=>'Réfrigérateur 300 L','variant'=>'Inox','quantity'=>3]]]))->assertRedirect()->assertSessionHasNoErrors();
        $saved=$quote->fresh();
        $this->assertSame(450000,$saved->lines[0]['unit_price']);
        $this->assertSame(3,$saved->lines[0]['quantity']);
        $this->assertSame([$lines[1]['description']],$saved->configuration['calculation']['pending']);
    }

    public function test_onsite_quote_can_start_from_an_article_universe(): void
    {
        $agent=$this->user('agent');
        $job=Intervention::create(['contact_id'=>$this->contact()->id,'agent_id'=>$agent->id,'kind'=>'devis_sur_place','scheduled_at'=>now()->addDay(),'address'=>'Abidjan']);
        $this->actingAs($agent)->post('/admin/prestations/'.$job->id.'/devis',['type'=>'meubles'])->assertRedirect();
        $quote=Quote::firstOrFail();
        $this->assertSame('meubles',$quote->type);
        $this->get('/admin/devis/'.$quote->id.'/formulaire')->assertOk()->assertSee('Montage');
    }

    public function test_catalogue_import_is_idempotent_unpublished_and_filters_by_universe(): void
    {
        $this->assertSame(0,Artisan::call('catalogue:import')); $count=Product::count();
        $this->assertGreaterThan(100,$count);
        $this->assertSame(0,Product::where('published',true)->count());
        $product=Product::where('details->universe','electromenager')->firstOrFail();
        $product->update(['published'=>true,'material_cost'=>1234]);
        $this->assertSame(0,Artisan::call('catalogue:import'));
        $this->assertSame($count,Product::count());
        $this->assertTrue($product->fresh()->published);
        $this->assertSame(1234,$product->fresh()->material_cost);
        $api=$this->getJson('/api/products')->assertOk()->getContent();
        $this->assertStringContainsString($product->reference,$api);
        $this->assertStringNotContainsString('reference_price',$api);
        $this->actingAs($this->user());
        $this->get('/admin/products?universe=meubles_bureau')->assertOk()->assertSee('Meubles Bureau')->assertDontSee($product->reference);
        $this->get('/admin/products?universe=electromenager')->assertOk()->assertSee($product->reference);
        $this->get('/admin/products/'.$product->id.'/edit')->assertOk()->assertSee('Prix relevé à titre indicatif');
    }

    public function test_article_catalogue_given_to_agents_has_no_cost_or_reference_price(): void
    {
        Artisan::call('catalogue:import');
        $html=$this->actingAs($this->user('agent'))->get('/admin/devis/nouveau?boutique=electromenager')->assertOk()->getContent();
        $this->assertStringContainsString('article-catalogue',$html);
        $this->assertStringNotContainsString('reference_price',$html);
        $this->assertStringNotContainsString('219900',$html);
    }

    public function test_profile_and_preferences_apply_only_to_the_signed_in_account(): void
    {
        $user=$this->user('agent'); $other=$this->user('agent'); $this->actingAs($user);
        $this->patch('/admin/profil',['name'=>'Nouveau Nom','phone'=>'+2250700000001','emergency_name'=>'Proche','role'=>'admin','active'=>0])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Nouveau Nom',$user->fresh()->name);
        $this->assertSame('agent',$user->fresh()->role);
        $this->assertTrue((bool)$user->fresh()->active);
        $this->assertNull($other->fresh()->profile);
        $this->patch('/admin/parametres',['theme'=>'neon'])->assertSessionHasErrors('theme');
        $this->patch('/admin/parametres',['theme'=>'dark','reduce_motion'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin')->assertOk()->assertSee('data-theme="dark"',false)->assertSee('data-motion="reduced"',false);
        $this->assertNull($other->fresh()->preferences);
    }

    public function test_emergency_contact_is_only_shown_in_the_admin_team_screen(): void
    {
        $agent=$this->user('agent'); $agent->update(['profile'=>['emergency_name'=>'Contact Privé']]);
        $this->actingAs($this->user())->get('/admin/equipe')->assertOk()->assertSee('Contact Privé');
        auth()->logout(); session()->flush();
        $this->actingAs($this->user('agent'))->get('/admin/equipe')->assertForbidden();
    }

    public function test_other_devices_logout_requires_the_current_password(): void
    {
        $this->actingAs($this->user());
        $this->post('/admin/parametres/autres-appareils',['password'=>'wrong'])->assertSessionHasErrors('password');
        $this->post('/admin/parametres/autres-appareils',['password'=>'TemporaryPass123!'])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_agent_field_sheet_guides_start_and_requires_a_report_to_finish(): void
    {
        $agent=$this->user('agent');
        $job=Intervention::create(['contact_id'=>$this->contact()->id,'agent_id'=>$agent->id,'kind'=>'installation','scheduled_at'=>now()->addHour(),'duration_minutes'=>60,'address'=>'Cocody','instructions'=>'Code 1234']);
        $this->actingAs($agent);
        $this->get('/admin')->assertOk()->assertSee('Ouvrir la fiche et démarrer')->assertSee('Code 1234');
        $this->get('/admin/prestations/'.$job->id)->assertOk()->assertSee('Démarrer l’intervention')->assertSee('tel:+2250700000000',false)->assertDontSee('Terminer l’intervention');
        $this->put('/admin/prestations/'.$job->id,['status'=>'in_progress','report'=>''])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/prestations/'.$job->id)->assertOk()->assertSee('Terminer l’intervention');
        $this->put('/admin/prestations/'.$job->id,['status'=>'completed','report'=>''])->assertSessionHasErrors('report');
        $this->put('/admin/prestations/'.$job->id,['status'=>'completed','report'=>'Stores posés'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/prestations/'.$job->id)->assertOk()->assertSee('Stores posés')->assertDontSee('Terminer l’intervention');
    }

    public function test_calendar_views_are_scoped_to_the_agent(): void
    {
        $agent=$this->user('agent'); $other=$this->user('agent');
        $mine=Appointment::create(['reference'=>(string)Str::uuid(),'date'=>today()->toDateString(),'time'=>'09:00','agent_id'=>$agent->id,'details'=>['firstName'=>'Awa','lastName'=>'Mine']]);
        $foreign=Appointment::create(['reference'=>(string)Str::uuid(),'date'=>today()->toDateString(),'time'=>'10:00','agent_id'=>$other->id,'details'=>['firstName'=>'Ali','lastName'=>'Autre']]);
        $this->actingAs($agent);
        foreach (['semaine','jour','liste'] as $view) $this->get('/admin/rendez-vous?vue='.$view)->assertOk()->assertSee(route('staff.appointments.show',$mine))->assertDontSee(route('staff.appointments.show',$foreign));
        $this->get('/admin/rendez-vous?vue=inconnue')->assertSessionHasErrors('vue');
    }

    public function test_quote_option_screen_reopens_only_the_failed_form_with_its_input(): void
    {
        $this->actingAs($this->user());
        $option=QuoteOption::create(['code'=>(string)Str::uuid(),'label'=>'Tissu existant','kind'=>'material','category'=>'rideau','fabric_group'=>'rideau','motorization'=>'any','active'=>true,'unit'=>'m','unit_price'=>1000]);
        $base=['tariff_form'=>1,'label'=>'','kind'=>'material','category'=>'rideau','motorization'=>'any','active'=>1,'unit'=>'m','fabric_group'=>'voilage','unit_price'=>4500];
        $this->from('/admin/options-devis')->post('/admin/options-devis',[...$base,'open_form'=>'new'])->assertRedirect('/admin/options-devis')->assertSessionHasErrors('label');
        $html=$this->get('/admin/options-devis')->assertOk()->getContent();
        $this->assertStringContainsString('aria-labelledby="h-ajouter-option"  data-open',$html);
        $this->assertSame(1,substr_count($html,'data-failed'));
        $this->assertStringContainsString('value="4500"',$html);
        $this->assertStringContainsString('id="form-errors"',$html);
        $this->assertStringNotContainsString('data-chip-item="material"  open',$html);
        $this->from('/admin/options-devis')->put('/admin/options-devis/'.$option->id,[...$base,'open_form'=>(string)$option->id])->assertSessionHasErrors('label');
        $html=$this->get('/admin/options-devis')->assertOk()->getContent();
        $this->assertStringContainsString('data-chip-item="material"  open',$html);
        $this->assertStringNotContainsString('aria-labelledby="h-ajouter-option"  data-open',$html);
        $this->assertSame(1,substr_count($html,'data-failed'));
        $this->assertSame('Tissu existant',$option->fresh()->label);
    }

    public function test_the_form_marker_is_never_stored_and_a_blank_price_stays_to_define(): void
    {
        $this->actingAs($this->user());
        $this->from('/admin/options-devis')->post('/admin/options-devis',['tariff_form'=>1,'open_form'=>'new','label'=>'Voilage sans prix','kind'=>'material','category'=>'rideau','motorization'=>'any','active'=>1,'unit'=>'m','fabric_group'=>'voilage','unit_price'=>''])->assertRedirect()->assertSessionHasNoErrors();
        $option=QuoteOption::where('label','Voilage sans prix')->firstOrFail();
        $this->assertNull($option->unit_price);
        $this->assertArrayNotHasKey('open_form',$option->getAttributes());
        $this->get('/admin/options-devis')->assertOk()->assertSee('À définir');
    }

    public function test_contact_errors_are_clear_and_the_panel_reopens(): void
    {
        $this->actingAs($this->user());
        $this->contact('Dossier existant')->update(['email'=>'deja@example.com']);
        $this->from('/admin/contacts')->post('/admin/contacts',['name'=>'Nouveau','kind'=>'client','email'=>'DEJA@example.com','marketing'=>1,'notes'=>'Note gardée'])->assertRedirect('/admin/contacts')->assertSessionHasErrors(['email','consent_source']);
        $this->get('/admin/contacts')->assertOk()
            ->assertSee('Cette adresse e-mail est déjà utilisée par un autre dossier.')
            ->assertSee('Indiquez l’origine du consentement')
            ->assertSee('Note gardée')
            ->assertSee('Rechercher le dossier qui utilise cette adresse')
            ->assertSee('aria-labelledby="h-nouveau-contact"  data-open',false);
    }

    public function test_agent_overlap_is_refused_and_the_planning_panel_reopens(): void
    {
        $this->actingAs($this->user());
        $agent=$this->user('agent'); $contact=$this->contact();
        $payload=['contact_id'=>$contact->id,'agent_id'=>$agent->id,'kind'=>'installation','scheduled_at'=>'2030-01-15T09:00','duration_minutes'=>60,'address'=>'Cocody'];
        $this->post('/admin/prestations',$payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->from('/admin/prestations')->post('/admin/prestations',[...$payload,'scheduled_at'=>'2030-01-15T09:30','instructions'=>'Consigne gardée'])->assertSessionHasErrors('scheduled_at');
        $this->get('/admin/prestations')->assertOk()->assertSee('Cet agent a déjà une prestation sur ce créneau.')->assertSee('Consigne gardée')->assertSee('aria-labelledby="h-planifier"  data-open',false);
    }

    public function test_creation_screens_stay_refused_to_agents_and_their_forms_show_no_price(): void
    {
        $this->seed();
        $this->actingAs($this->user('agent'));
        foreach (['/admin/contacts','/admin/options-devis','/admin/products/create','/admin/contents/create','/admin/mise-en-page/quote'] as $url) $this->get($url)->assertForbidden();
        foreach (['/admin/contacts','/admin/options-devis','/admin/products','/admin/contents','/admin/prestations','/admin/mise-en-page/quote'] as $url) $this->post($url,[])->assertForbidden();
        $this->post('/admin/devis/chiffrer',[])->assertForbidden();
        $this->get('/admin/prestations')->assertOk()->assertDontSee('planifier-form');
        foreach (['/admin/devis/nouveau','/admin/devis/nouveau?boutique=electromenager','/admin/devis/nouveau?boutique=meubles'] as $url) {
            $response=$this->get($url)->assertOk()->assertHeader('X-Robots-Tag','noindex, nofollow')->assertDontSee('fbq(',false);
            $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
            foreach (['FCFA','F CFA','unit_price','Prix unitaire','calculation_token','internal_notes','_cost','reference_price'] as $secret) $this->assertStringNotContainsString($secret,$response->getContent(),$url.' expose '.$secret);
        }
        $options=json_decode(Str::betweenFirst($this->get('/admin/devis/nouveau')->getContent(),'<script type="application/json" id="agent-catalogue">','</script>'),true)['options'];
        $this->assertNotEmpty($options);
        foreach ($options as $option) $this->assertSame(['category','code','id','label','motorization'],collect(array_keys($option))->sort()->values()->all());
    }
}
