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
        $html=$this->get('/admin/contacts')->assertOk()
            ->assertSee('Cette adresse e-mail est déjà utilisée par un autre dossier.')
            ->assertSee('Indiquez l’origine du consentement')
            ->assertSee('Note gardée')
            ->assertSee('Rechercher le dossier qui utilise cette adresse')
            ->getContent();
        $this->assertMatchesRegularExpression('/class="float-panel" id="nouveau-contact"\s+data-open/',$html);
    }

    public function test_agent_overlap_is_refused_and_the_planning_panel_reopens(): void
    {
        $this->actingAs($this->user());
        $agent=$this->user('agent'); $contact=$this->contact();
        $payload=['contact_id'=>$contact->id,'agent_id'=>$agent->id,'kind'=>'installation','scheduled_at'=>'2030-01-15T09:00','duration_minutes'=>60,'address'=>'Cocody'];
        $this->post('/admin/prestations',$payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->from('/admin/prestations')->post('/admin/prestations',[...$payload,'scheduled_at'=>'2030-01-15T09:30','instructions'=>'Consigne gardée'])->assertSessionHasErrors('scheduled_at');
        $html=$this->get('/admin/prestations')->assertOk()->assertSee('Cet agent a déjà une prestation sur ce créneau.')->assertSee('Consigne gardée')->getContent();
        $this->assertMatchesRegularExpression('/class="float-panel" id="planifier"\s+data-open/',$html);
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

    public function test_quote_openings_table_keeps_each_field_once_and_the_summary_reads_the_form(): void
    {
        $this->seed();
        $agent=$this->user('agent');
        $this->actingAs($this->user());
        $html=$this->withSession(['_old_input'=>['contact'=>['firstName'=>'Awa','lastName'=>'Client','phone'=>'0700000001'],'agent_id'=>$agent->id,
            'windows'=>[['label'=>'Salon','width'=>240,'height'=>260,'quantity'=>2,'inherit_defaults'=>1],['label'=>'Chambre','width'=>180,'height'=>220,'quantity'=>1,'inherit_defaults'=>1]]]])
            ->get('/admin/devis/nouveau')->assertOk()->getContent();
        // Le rail et le coefficient passent dans la ligne de l'ouverture : chaque champ reste envoyé une seule fois.
        foreach ([0,1] as $index) foreach (['[costing][material_coefficient]','[costing][support][option_id]','[costing][support][quantity]','[inherit_defaults]" value="1"'] as $field)
            $this->assertSame(1,substr_count($html,'name="windows['.$index.']'.$field.(str_ends_with($field,'"') ? '' : '"')),$index.$field);
        $recap=Str::betweenFirst($html,'id="quote-recap"','</aside>');
        foreach (['Awa Client · 0700000001','Salon 240 × 260 ×2 · Chambre 180 × 220',$agent->name,'À calculer à l’étape 3'] as $text) $this->assertStringContainsString(e($text),$recap);
        $this->assertStringContainsString('data-pending="[]"',$recap);
        $this->flushSession();
        $this->actingAs($agent)->get('/admin/devis/nouveau')->assertOk()->assertDontSee('quote-recap');
    }

    public function test_agent_planning_shows_only_busy_slots_and_only_to_the_admin(): void
    {
        $agent=$this->user('agent'); $other=$this->user('agent'); $contact=$this->contact();
        $job=fn(array $values)=>Intervention::create([...['contact_id'=>$contact->id,'agent_id'=>$agent->id,'kind'=>'installation','status'=>'planned','duration_minutes'=>90,'address'=>'Adresse secrète','instructions'=>'Consigne secrète'],...$values]);
        $job(['scheduled_at'=>now()->addDay()->setTime(9,0)]);
        $job(['scheduled_at'=>now()->addDays(2)->setTime(14,0),'agent_id'=>$other->id,'kind'=>'sav','status'=>'in_progress']);
        $job(['scheduled_at'=>now()->addDay()->setTime(11,0),'status'=>'cancelled']);
        $job(['scheduled_at'=>now()->addDay()->setTime(15,0),'status'=>'completed']);
        $job(['scheduled_at'=>now()->addDays(90)->setTime(9,0)]);
        $this->actingAs($this->user());
        $html=$this->get('/admin/prestations')->assertOk()->getContent();
        $planning=json_decode(Str::betweenFirst($html,'<script type="application/json" id="agent-planning">','</script>'),true);
        $this->assertSame([today()->format('Y-m-d'),today()->addDays(60)->format('Y-m-d')],[$planning['from'],$planning['to']]);
        $this->assertSame([
            ['agent'=>$agent->id,'kind'=>'installation','start'=>now()->addDay()->format('Y-m-d').'T09:00','end'=>now()->addDay()->format('Y-m-d').'T10:30'],
            ['agent'=>$other->id,'kind'=>'sav','start'=>now()->addDays(2)->format('Y-m-d').'T14:00','end'=>now()->addDays(2)->format('Y-m-d').'T15:30'],
        ],$planning['jobs']);
        $this->assertStringNotContainsString('secrète',Str::betweenFirst($html,'id="agent-planning">','</script>'));
        $this->flushSession();
        $this->actingAs($agent)->get('/admin/prestations')->assertOk()->assertDontSee('agent-planning')->assertDontSee('data-agent-day',false);
    }

    private function layoutSettings(int $version,string $action='draft',array $override=[]): array
    {
        return [...\App\Services\DocumentLayout::defaults(),'version'=>$version,'name'=>'Modèle test','body'=>'','issuer_name'=>'Maison du Store AM','issuer_address'=>'Abidjan',
            'issuer_email'=>'contact@example.invalid','scenario'=>'single','preview_type'=>'quote','action'=>$action,...$override];
    }

    public function test_layout_blocks_are_saved_as_draft_published_and_refused_to_agents(): void
    {
        $this->seed();
        $template=fn()=>\App\Models\CommunicationTemplate::where('channel','pdf')->where('key','quote')->firstOrFail();
        $blocks=json_encode(\App\Services\DocumentBlocks::starter());
        $agent=$this->user('agent');
        $this->actingAs($agent)->post('/admin/mise-en-page/quote',$this->layoutSettings($template()->version,'draft',['blocks'=>$blocks]))->assertForbidden();
        $this->post('/admin/mise-en-page/quote/apercu',$this->layoutSettings($template()->version,'draft',['blocks'=>$blocks]))->assertForbidden();
        $this->assertNull($template()->layout_draft);
        $this->flushSession();
        $this->actingAs($this->user());
        $this->post('/admin/mise-en-page/quote',$this->layoutSettings($template()->version,'draft',['blocks'=>'{"items":[{"type":"script","x":1,"y":1,"w":10,"h":10,"pages":["single"]}]}']))->assertSessionHasErrors('blocks');
        $this->post('/admin/mise-en-page/quote',$this->layoutSettings($template()->version,'draft',['blocks'=>$blocks]))->assertSessionHasNoErrors();
        // Le brouillon garde les blocs ; le modèle publié, utilisé par les documents, n'en a pas encore.
        $this->assertCount(13,$template()->layout_draft['options']['blocks']['items']);
        $this->assertArrayNotHasKey('blocks',\App\Services\DocumentLayout::presentation('quote'));
        $this->post('/admin/mise-en-page/quote/apercu',$this->layoutSettings($template()->version,'draft',['blocks'=>$blocks]))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->post('/admin/mise-en-page/quote',$this->layoutSettings($template()->version,'publish',['blocks'=>$blocks]))->assertSessionHasNoErrors();
        $published=\App\Services\DocumentLayout::presentation('quote')['blocks'];
        $this->assertSame(['single','continuation'],$published['items'][0]['pages']);
        $this->assertEquals(['x'=>15,'y'=>40],['x'=>$published['items'][0]['x'],'y'=>$published['items'][0]['y']]);
        $this->assertNull($template()->layout_draft);
        // Sans champ « blocks » (sans JavaScript), les blocs restent ; un champ vide revient au rendu actuel.
        $this->post('/admin/mise-en-page/quote',$this->layoutSettings($template()->version,'publish'))->assertSessionHasNoErrors();
        $this->assertCount(13,\App\Services\DocumentLayout::presentation('quote')['blocks']['items']);
        $revision=\Illuminate\Support\Facades\DB::table('document_template_revisions')->where('communication_template_id',$template()->id)->latest('id')->first();
        $this->assertStringContainsString('"blocks"',$revision->snapshot);
        $this->post('/admin/mise-en-page/quote',$this->layoutSettings($template()->version,'publish',['blocks'=>'']))->assertSessionHasNoErrors();
        $this->assertNull(\App\Services\DocumentLayout::presentation('quote')['blocks']);
    }

    public function test_block_layout_continues_on_pages_and_templates_without_blocks_keep_their_rendering(): void
    {
        $presentation=[...\App\Services\DocumentLayout::defaults(),'blocks'=>\App\Services\DocumentBlocks::starter()];
        $long=\App\Services\DocumentViewModel::prepare(\App\Services\DocumentPreview::data('quote','thirty',$presentation));
        $pages=\App\Services\DocumentBlocks::pages($long);
        $this->assertGreaterThan(1,count($pages));
        $last=end($pages);
        $this->assertSame('single',$last['kind']);
        foreach (array_slice($pages,0,-1) as $page) {
            $this->assertSame('continuation',$page['kind']);
            // La page suite reprend l'en-tête, sans total ni conditions.
            $this->assertEqualsCanonicalizing(['logo','company','title','lines','footer'],array_column($page['items'],'type'));
        }
        $rows=collect($pages)->flatMap(fn($page)=>collect($page['items'])->where('type','lines')->flatMap(fn($b)=>$b['rows']));
        $lines=collect($last['items'])->firstWhere('type','lines');
        $this->assertCount(count(\App\Services\DocumentBlocks::rows($long,$lines)),$rows);
        $this->assertNotEmpty($lines['rows']);
        $this->assertSame('Pose et livraison',$lines['rows'][array_key_last($lines['rows'])]['label']);
        // Le total remonte juste sous la dernière ligne ; les signatures restent en place.
        $total=collect($last['items'])->firstWhere('type','total');
        $this->assertEqualsWithDelta($lines['y']+$lines['h']+2,$total['y'],0.2);
        $starter=collect(\App\Services\DocumentBlocks::starter()['items'])->keyBy('id');
        $this->assertSame($starter['signature']['y'],collect($last['items'])->firstWhere('type','signature')['y']);
        $pdf=app(\App\Services\Documents::class)->render(\App\Services\DocumentPreview::data('quote','thirty',$presentation));
        $this->assertSame(count($pages),preg_match_all('/\/Type\s*\/Page[^s]/',$pdf));
        $this->assertCount(1,\App\Services\DocumentBlocks::pages(\App\Services\DocumentViewModel::prepare(\App\Services\DocumentPreview::data('quote','single',$presentation))));
        // Sans blocs, la vue rend exactement le même HTML qu'un modèle aux blocs vides, sans aucune page par blocs.
        foreach (['quote','invoice','receipt'] as $kind) {
            $plain=\App\Services\DocumentViewModel::prepare(\App\Services\DocumentPreview::data($kind,'thirty',\App\Services\DocumentLayout::defaults()));
            $empty=\App\Services\DocumentViewModel::prepare(\App\Services\DocumentPreview::data($kind,'thirty',[...\App\Services\DocumentLayout::defaults(),'blocks'=>null]));
            $html=view('documents.pdf',$plain)->render();
            $this->assertSame($html,view('documents.pdf',$empty)->render());
            $this->assertStringNotContainsString('class="bp',$html);
            $this->assertStringStartsWith('<!DOCTYPE html><html lang="fr">',$html);
        }
    }

    public function test_installment_schedule_follows_the_total_and_quotes_without_it_keep_their_deposit(): void
    {
        $totals=fn(int $amount,array $options)=>\App\Services\Quotes\QuoteTotals::calculate($amount,0,$options);
        $steps=fn(array $result)=>array_map(fn($step)=>[$step['percent'],$step['amount']],$result['schedule']);
        // Jusqu'à 500 000 F inclus : 2 échéances, acompte réglable puis solde.
        $limit=$totals(500000,['schedule'=>'auto','depositPercent'=>70]);
        $this->assertSame(['auto','two'],[$limit['scheduleChoice'],$limit['scheduleMode']]);
        $this->assertSame([['70',350000],['30',150000]],$steps($limit));
        // Au-delà : 50 / 30 / 20, chaque échéance au franc, la dernière reprend le reste.
        $above=$totals(500001,['schedule'=>'auto','depositPercent'=>70]);
        $this->assertSame([['50',250001],['30',150000],['20',100000]],$steps($above));
        $this->assertSame([257032,154219,102813],array_column($totals(514064,['schedule'=>'auto'])['schedule'],'amount'));
        foreach ([500001,514064,999999,1234567] as $amount) $this->assertSame($amount,array_sum(array_column($totals($amount,['schedule'=>'three'])['schedule'],'amount')));
        // L'acompte enregistré reste la première échéance : état de paiement et lancement de la confection en dépendent.
        $this->assertSame([257032,'50',257032],[$totals(514064,['schedule'=>'auto'])['deposit'],$totals(514064,['schedule'=>'auto'])['depositPercent'],$totals(514064,['schedule'=>'auto'])['balance']]);
        $this->assertTrue($totals(514064,['schedule'=>'auto','receivedAmount'=>257032])['productionAuthorized']);
        // Devis de référence : 443 916 F, acompte de 60 % choisi, sous le seuil : montants inchangés.
        $reference=$totals(443916,['schedule'=>'auto','depositPercent'=>60]);
        $legacy=$totals(443916,['depositPercent'=>60]);
        $this->assertSame([266350,177566],[$reference['deposit'],$reference['balance']]);
        $this->assertSame([$legacy['deposit'],$legacy['balance'],$legacy['depositPercent']],[$reference['deposit'],$reference['balance'],$reference['depositPercent']]);
        // Sans choix d'échéancier (devis déjà chiffrés), le calcul ne change pas.
        $this->assertArrayNotHasKey('schedule',$legacy);
        $this->assertSame(['70',359845],[$totals(514064,[])['depositPercent'],$totals(514064,[])['deposit']]);
        $this->expectException(\InvalidArgumentException::class);
        $totals(100000,['schedule'=>'four']);
    }

    public function test_schedule_and_commitment_print_only_for_quotes_that_have_a_schedule(): void
    {
        $render=function (array $data) { return view('documents.pdf',\App\Services\DocumentViewModel::prepare($data))->render(); };
        $data=\App\Services\DocumentPreview::data('quote','thirty',\App\Services\DocumentLayout::defaults());
        $html=$render($data);
        $this->assertStringContainsString('APRÈS INSTALLATION DES RAILS (30 %)',$html);
        $this->assertStringContainsString('Notre engagement.',$html);
        $this->assertStringContainsString(e(\App\Services\DocumentLayout::COMMITMENT),$html);
        $this->assertStringContainsString('Échéancier : 50 % à la validation du chantier',$html);
        // Un devis chiffré avant l'échéancier garde son acompte unique et n'affiche pas la mention.
        foreach (['schedule','scheduleMode','scheduleChoice'] as $key) unset($data['financial'][$key]);
        $old=$render($data);
        $this->assertStringNotContainsString('Notre engagement',$old);
        $this->assertStringContainsString('Un acompte de 50 % est obligatoire',$old);
        // Une facture ne porte pas la mention, et le texte se modifie dans le modèle.
        $this->assertStringNotContainsString('Notre engagement',$render(\App\Services\DocumentPreview::data('invoice','thirty',\App\Services\DocumentLayout::defaults())));
        $custom=$render(\App\Services\DocumentPreview::data('quote','thirty',[...\App\Services\DocumentLayout::defaults(),'commitment_text'=>'Engagement de test.']));
        $this->assertStringContainsString('Notre engagement.</strong> Engagement de test.',$custom);
        $blocks=\App\Services\DocumentBlocks::payment(\App\Services\DocumentViewModel::prepare(\App\Services\DocumentPreview::data('quote','thirty',\App\Services\DocumentLayout::defaults())));
        $this->assertStringStartsWith('50 % — Acompte à la validation du chantier : ',$blocks['lines'][0]);
        $this->assertCount(4,$blocks['lines']);
    }

    public function test_manager_has_the_office_but_not_the_site_settings_and_agents_keep_their_shops(): void
    {
        $owner=$this->user(); $manager=$this->user('manager'); $agent=$this->user('agent');
        $owned=['/admin/visiteurs','/admin/equipe','/admin/mise-en-page','/admin/communications','/admin/contents'];
        $office=['/admin/devis','/admin/devis/nouveau','/admin/rendez-vous','/admin/prestations','/admin/messages','/admin/factures','/admin/products','/admin/contacts','/admin/options-devis'];
        $this->actingAs($manager);
        foreach ($office as $url) $this->get($url)->assertOk();
        foreach ($owned as $url) $this->get($url)->assertForbidden();
        $this->post('/admin/equipe',['username'=>'pirate','name'=>'X','email'=>'x@example.com','role'=>'admin','password'=>'TemporaryPass123!'])->assertForbidden();
        $this->put('/admin/equipe/'.$agent->id,['active'=>1,'role'=>'admin'])->assertForbidden();
        $this->assertSame('agent',$agent->fresh()->role);
        $menu=$this->get('/admin')->assertOk();
        $menu->assertSee('Manager')->assertSee('/admin/products',false);
        foreach (['/admin/visiteurs','/admin/equipe','/admin/communications','/admin/mise-en-page','/admin/contents'] as $url) $menu->assertDontSee($url.'"',false);
        $this->get('/admin/devis/nouveau')->assertSee('pricing[deposit_percent]',false);
        // L'admin principal garde tout et règle les rôles et les boutiques des agents.
        $this->flushSession(); $this->actingAs($owner);
        foreach ($owned as $url) $this->assertContains($this->get($url)->status(),[200,302],$url);
        $this->put('/admin/equipe/'.$agent->id,['active'=>1,'role'=>'agent','shops_sent'=>1,'shops'=>['electromenager']])->assertRedirect();
        $this->put('/admin/equipe/'.$agent->id,['active'=>1,'shops_sent'=>1,'shops'=>['cuisine']])->assertSessionHasErrors('shops.0');
        $this->assertSame(['electromenager'],$agent->fresh()->profile['shops']);
        $this->post('/admin/equipe',['username'=>'manager2','name'=>'Manager Deux','email'=>'m2@example.com','role'=>'manager','password'=>'TemporaryPass123!'])->assertRedirect();
        $this->assertSame('manager',User::where('username','manager2')->value('role'));
        // L'agent n'ouvre que ses boutiques, même en forçant l'adresse ou le formulaire.
        $this->flushSession(); $this->actingAs($agent->fresh());
        $this->get('/admin/devis/nouveau')->assertRedirect('/admin/devis/nouveau?boutique=electromenager');
        $this->get('/admin/devis/nouveau?boutique=meubles')->assertRedirect('/admin/devis/nouveau?boutique=electromenager');
        $this->get('/admin/devis/nouveau?boutique=electromenager')->assertOk()->assertDontSee('id="boutique"',false);
        $this->post('/admin/devis',$this->article(['universe'=>'meubles']))->assertForbidden();
        $this->post('/admin/devis',$this->article())->assertRedirect();
        foreach ([...$owned,'/admin/factures','/admin/products'] as $url) $this->get($url)->assertForbidden();
    }

    public function test_agent_profile_keeps_cover_availability_and_skills_as_checkboxes(): void
    {
        $agent=$this->user('agent'); $other=$this->user('agent');
        $this->actingAs($agent);
        $page=$this->get('/admin/profil')->assertOk();
        foreach (['enctype="multipart/form-data"','name="cover"','name="skill_list[]" value="motorisation"','name="days[]" value="sam"','name="availability" value="available"','name="card_bg" value="sand"','data-card="foot"'] as $html) $page->assertSee($html,false);
        $this->get('/admin/profil/couverture')->assertNotFound();
        $this->patch('/admin/profil',['name'=>'Agent Test','availability'=>'available','skill_list'=>['stores','motorisation'],'days'=>['lun','sam'],'skills'=>'Tapisserie',
            'cover'=>\Illuminate\Http\UploadedFile::fake()->image('couverture.png',1200,300)])->assertSessionHasNoErrors();
        $profile=$agent->fresh()->profile;
        $this->assertSame(['stores','motorisation'],$profile['skill_list']); $this->assertSame(['lun','sam'],$profile['days']); $this->assertSame('available',$profile['availability']);
        $this->get('/admin/profil/couverture')->assertOk()->assertHeader('Content-Type','image/png');
        $this->get('/admin/profil')->assertSee('class="profile-cover"',false)->assertSee('data-cover=',false);
        // Cases décochées : listes vides et statut absent ; un champ réservé au bureau est ignoré.
        $this->patch('/admin/profil',['name'=>'Agent Test','availability'=>'away','shops'=>['meubles'],'signature'=>'X'])->assertSessionHasNoErrors();
        $profile=$agent->fresh()->profile;
        $this->assertSame([],$profile['skill_list']); $this->assertSame('away',$profile['availability']);
        $this->assertArrayNotHasKey('shops',$profile); $this->assertArrayNotHasKey('signature',$profile);
        $this->patch('/admin/profil',['name'=>'Agent Test','skill_list'=>['pilote']])->assertSessionHasErrors('skill_list.0');
        $this->patch('/admin/profil',['name'=>'Agent Test','cover'=>\Illuminate\Http\UploadedFile::fake()->create('cv.pdf',5,'application/pdf')])->assertSessionHasErrors('cover');
        // Chacun ne lit que sa propre couverture.
        $cover=$agent->fresh()->profile['cover_media_id'];
        $this->flushSession(); $this->actingAs($other);
        $this->get('/admin/profil/couverture')->assertNotFound();
        $this->flushSession(); $this->actingAs($agent->fresh());
        $this->patch('/admin/profil',['name'=>'Agent Test','remove_cover'=>1])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('cover_media_id',$agent->fresh()->profile);
        $this->assertNull(\App\Models\MediaAsset::find($cover));
    }
}
