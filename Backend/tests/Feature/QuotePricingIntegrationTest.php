<?php
namespace Tests\Feature;

use App\Models\{Quote,QuoteOption,User,Invoice};
use App\Services\{CommunicationTemplates,Documents,QuoteCosting};
use App\Services\Quotes\{PricingConfig,QuoteFinancials};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotePricingIntegrationTest extends TestCase
{
    use RefreshDatabase;
    private function login(string $role='admin'): User
    {
        auth()->logout(); session()->flush();
        $u=User::create(['username'=>Str::uuid(),'name'=>'Test','email'=>Str::random(8).'@example.invalid','password'=>'TestOnly123!','role'=>$role,'must_change_password'=>false]);
        $this->actingAs($u); return $u;
    }
    private function id(string $code): int { return QuoteOption::where('code',$code)->value('id'); }
    private function payload(): array
    {
        $s=['category'=>'rideau','curtain_id'=>$this->id('occultant-80'),'sheer_id'=>null,'store_id'=>null,'finish_id'=>$this->id('reflete-manuel'),'motorized'=>0];
        $cost=['zone'=>'abidjan','material_quantity'=>'5','support'=>['option_id'=>$this->id('rail-simple')],
            'tape'=>['option_id'=>$this->id('reflete-4')], 'sewing'=>['option_id'=>$this->id('confection')]];
        $window=[...$s,'inherit_defaults'=>1,'label'=>'Fenêtre 1','width'=>'200','height'=>'210','quantity'=>2,'costing'=>$cost];
        $fees=[];foreach (PricingConfig::FEES as $key=>$label) $fees[$key]=['enabled'=>$key==='installation_delivery'?1:0,'unit_price'=>$key==='installation_delivery'?30000:0,'scope'=>'global','quantity'=>1];
        return ['defaults'=>$s,'windows'=>[$window,[...$window,'label'=>'Fenêtre 2','height'=>'280','quantity'=>1],
            [...$window,'label'=>'Fenêtre 3','width'=>'150','height'=>'260','quantity'=>1,'costing'=>[...$cost,'material_quantity'=>'3.75']]],
            'pricing'=>['engine'=>2,'display_mode'=>'grouped','discount_type'=>'none','discount_value'=>0,'deposit_percent'=>70,'fees'=>$fees],
            'tax_rate'=>0,'submission_token'=>(string)Str::uuid(),'receipt_token'=>(string)Str::uuid(),'edit_version'=>0,
            'contact'=>['lastName'=>'Client test','phone'=>'+2250700000000','address'=>'Abidjan'], 'quote_date'=>today()->toDateString(),'valid_until'=>today()->addDays(30)->toDateString(),
            'use_catalogue'=>1,'payment_mode'=>'advance','deposit_amount'=>0];
    }
    private function calculated(array $data): array
    {
        $result=$this->postJson('/admin/devis/chiffrer',$data)->assertOk()->json();
        return [...$data,'lines'=>$result['lines'],'calculation_token'=>$result['token']];
    }
    private function save(array $data): Quote
    {
        $this->post('/admin/devis',$this->calculated($data))->assertSessionHasNoErrors();return Quote::latest('id')->firstOrFail();
    }
    public function test_reference_example_is_saved_and_shared_by_pdf_invoice_and_communications(): void
    {
        $this->seed();$this->login();$data=$this->payload();$q=$this->save($data);
        $f=QuoteFinancials::forQuote($q);$this->assertSame(261750,$f['total']);$this->assertSame(183225,$q->deposit_amount);$this->assertSame(78525,$f['balance']);
        $this->get('/admin/devis/'.$q->id)->assertOk()->assertSee('261 750')->assertSee('183 225');
        $this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk()->assertSee('261 750');
        $context=CommunicationTemplates::context($q);$this->assertSame('261 750',$context['montant']);$this->assertSame('78 525',$context['solde']);
        $doc=app(Documents::class)->quoteData($q);$html=view('staff.document',QuoteFinancials::document($doc))->render();
        $this->assertStringContainsString('261 750',$html);$this->assertStringContainsString('solde de 30 %',$html);$this->assertStringNotContainsString('80 %',$html);
        $this->get('/admin/devis/'.$q->id.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        QuoteOption::where('code','occultant-80')->update(['unit_price'=>99000]);$this->assertSame(261750,QuoteFinancials::forQuote($q->fresh())['total']);
        $this->put('/admin/devis/'.$q->id,['lines'=>$q->lines,'tax_rate'=>0,'valid_until'=>$q->valid_until,'status'=>'accepted'])->assertSessionHasNoErrors();
        $this->post('/admin/devis/'.$q->id.'/facture',['due_date'=>today()->addDays(10)->toDateString()])->assertSessionHasNoErrors();
        $invoice=Invoice::firstOrFail();$this->assertSame(261750,QuoteFinancials::forSnapshot($invoice->snapshot)['total']);
        $this->assertSame('261 750',CommunicationTemplates::context($invoice)['montant']);
        $this->get('/admin/factures/'.$invoice->id)->assertOk()->assertSee('183 225');
        $this->get('/admin/factures/'.$invoice->id.'/pdf')->assertOk();
    }
    public function test_negotiated_total_and_manual_overrides_survive_recalculation_and_catalogue_changes(): void
    {
        $this->seed();$this->login();$data=$this->payload();
        $data['windows'][0]['costing']['material_quantity']='6.625';$data['windows'][0]['costing']['material_unit_price']=10000;
        $data['pricing']['discount_type']='target_total';$data['pricing']['discount_value']=200000;
        $first=$this->postJson('/admin/devis/chiffrer',$data)->assertOk()->json('financial');
        QuoteOption::where('code','occultant-80')->update(['unit_price'=>15000]);
        $second=$this->postJson('/admin/devis/chiffrer',$data)->assertOk()->json('financial');
        $this->assertSame($first['openings'][0],$second['openings'][0]);$this->assertSame('6.625',$second['openings'][0]['lines'][0]['quantity']);
        $this->assertSame('manual',$second['openings'][0]['lines'][0]['priceSource']);$this->assertSame(200000,$second['total']);$this->assertSame(140000,$second['deposit']);
        $q=$this->save($data);$this->assertSame(200000,QuoteFinancials::forQuote($q)['total']);
        $this->assertSame(15000,QuoteOption::where('code','occultant-80')->first()->unit_price);
    }
    public function test_signed_results_cannot_be_edited_without_recalculating(): void
    {
        $this->seed();$this->login();$data=$this->calculated($this->payload());
        $bad=$data;$bad['lines'][0]['unit_price']=1;$this->post('/admin/devis',$bad)->assertSessionHasErrors('lines');
        $bad=$data;$bad['pricing']['discount_value']=10;$this->post('/admin/devis',$bad)->assertSessionHasErrors('calculation_token');
        $bad=$data;$bad['windows'][0]['costing']['material_unit_price']=1;$this->post('/admin/devis',$bad)->assertSessionHasErrors('calculation_token');
        $this->assertDatabaseCount('quotes',0);
    }
    public function test_individual_openings_do_not_require_unused_common_choices_for_calculation_or_save(): void
    {
        $this->seed();$this->login();$data=$this->payload();
        $data['defaults']=['category'=>'store','motorized'=>0,'curtain_id'=>null,'sheer_id'=>null,'store_id'=>null,'finish_id'=>null];
        foreach ($data['windows'] as &$window) $window['inherit_defaults']=0;
        unset($window);
        $q=$this->save($data);$this->assertSame(261750,QuoteFinancials::forQuote($q)['total']);
        $this->assertSame('rideau',$q->type);
        foreach ($q->dimensions as $window) $this->assertFalse($window['inherit_defaults']);
        // Agents can also save their independent technical selections without common choices.
        $agent=$this->login('agent');unset($data['pricing'],$data['tax_rate'],$data['payment_mode'],$data['deposit_amount'],$data['receipt_token']);
        $data=$this->agentPayload($data);$data['submission_token']=(string)Str::uuid();
        $this->post('/admin/devis',$data)->assertSessionHasNoErrors();
        $technical=Quote::latest('id')->firstOrFail();$this->assertSame($agent->id,$technical->agent_id);$this->assertTrue($technical->configuration['pricing_review_required']);
    }
    public function test_used_common_choices_still_require_material_and_finish_with_clear_messages(): void
    {
        $this->seed();$this->login();$data=$this->payload();
        $data['defaults']['curtain_id']=null;$data['defaults']['finish_id']=null;
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertUnprocessable()->assertJsonValidationErrors(['defaults.material_id','defaults.finish_id']);
        $this->assertStringContainsString('Choix communs : sélectionnez',$r->json('errors')['defaults.material_id'][0]);
    }
    public function test_inherited_choices_are_resolved_on_server_even_without_duplicate_window_fields(): void
    {
        $this->seed();$this->login();$data=$this->payload();
        foreach ($data['windows'] as &$window) foreach (['category','motorized','curtain_id','sheer_id','store_id','finish_id','material_id'] as $key) unset($window[$key]);
        unset($window);
        $q=$this->save($data);$this->assertSame(261750,QuoteFinancials::forQuote($q)['total']);
    }
    public function test_catalogue_global_fee_is_frozen_until_explicit_recalculation(): void
    {
        $this->seed();$this->login();$data=$this->payload();
        $option=QuoteOption::create(['code'=>'global-fee-test','label'=>'Pose et livraison','kind'=>'combined','category'=>'rideau','unit'=>'flat','unit_price'=>30000,'active'=>true,'motorization'=>'any']);
        $data['pricing']['fees']['installation_delivery']['option_id']=$option->id;$data['pricing']['fees']['installation_delivery']['unit_price']=null;
        $signed=$this->calculated($data);$option->update(['unit_price'=>50000]);
        $this->post('/admin/devis',$signed)->assertSessionHasNoErrors();$q=Quote::firstOrFail();
        $this->assertSame(261750,QuoteFinancials::forQuote($q)['total']);
        $this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk();
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();$this->assertSame(281750,$r->json('financial.total'));
        $data['pricing']['fees']['installation_delivery']['unit_price']=0;
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();$this->assertSame(231750,$r->json('financial.total'));
    }
    public function test_global_pose_cannot_duplicate_per_opening_pose_and_zero_fees_are_allowed(): void
    {
        $this->seed();$this->login();$data=$this->payload();
        $data['windows'][0]['costing']['installation']=['option_id'=>$this->id('pose-rideaux'),'quantity'=>1,'unit_price'=>1000];
        $this->postJson('/admin/devis/chiffrer',$data)->assertUnprocessable()->assertJsonValidationErrors('windows.0.costing.installation.option_id');
        $data=$this->payload();$data['pricing']['fees']['delivery']['enabled']=1;
        $this->postJson('/admin/devis/chiffrer',$data)->assertUnprocessable()->assertJsonValidationErrors('pricing.fees');
        $data=$this->payload();$data['pricing']['fees']['installation_delivery']['unit_price']=0;
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();$this->assertSame(231750,$r->json('financial.total'));$this->assertSame([],$r->json('pending'));
    }
    public function test_motorized_curtain_accepts_extra_manual_rail_heavy_voile_and_fixed_confection(): void
    {
        $this->seed();$this->login();$data=$this->payload();$data['defaults']['motorized']=1;$data['defaults']['finish_id']=$this->id('wave-electric');$data['defaults']['sheer_id']=$this->id('voilage-lourd');
        $data['windows']=[$data['windows'][0]];$data['windows'][0]['quantity']=1;
        $cost=&$data['windows'][0]['costing'];$cost['support']=['option_id'=>$this->id('rail-wave-electric')];unset($cost['tape']);
        $cost['secondary_material']=['quantity'=>4];$cost['extra_support']=['option_id'=>$this->id('rail-simple')];
        $cost['motor']=['option_id'=>$this->id('kit-moteur-rideau'),'quantity'=>1];$cost['sewing']=['option_id'=>$this->id('confection'),'mode'=>'fixed','unit_price'=>25000];
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();$this->assertSame(352000,$r->json('financial.total'));
        $q=$this->save($data);$this->assertSame(352000,QuoteFinancials::forQuote($q)['total']);
    }
    public function test_agents_cannot_see_or_submit_any_new_prices(): void
    {
        $this->seed();$this->login();$q=$this->save($this->payload());$agent=$this->login('agent');$q->update(['agent_id'=>$agent->id,'created_by'=>$agent->id]);
        foreach (['','/formulaire'] as $path) {
            $html=$this->get('/admin/devis/'.$q->id.$path)->assertOk()->getContent();
            foreach (['unit_price','261750','261 750','183225','discount_type','F CFA'] as $secret) $this->assertStringNotContainsString($secret,$html);
        }
        $this->postJson('/admin/devis/chiffrer',$this->payload())->assertForbidden();
        $this->post('/admin/devis/'.$q->id.'/avancement',['stage'=>'in_production','edit_version'=>0])->assertForbidden();
        $data=$this->payload();unset($data['pricing'],$data['tax_rate'],$data['payment_mode'],$data['deposit_amount']);
        foreach (['material_unit_price','finish_unit_price'] as $key) {
            $bad=$data;$bad['windows'][0]['costing'][$key]=1;$this->put('/admin/devis/'.$q->id.'/formulaire',$bad)->assertSessionHasErrors('windows.0.costing');
        }
        $bad=$data;$bad['windows'][0]['costing']['support']['unit_price']=1;$this->put('/admin/devis/'.$q->id.'/formulaire',$bad)->assertSessionHasErrors('windows.0.costing');
    }
    public function test_production_is_blocked_until_confirmed_deposit_and_overpayments_are_rejected(): void
    {
        $this->seed();$this->login();$data=$this->payload();$q=$this->save($data);$q->update(['status'=>'accepted']);
        $url='/admin/devis/'.$q->id;
        $this->post($url.'/avancement',['stage'=>'in_production','edit_version'=>$q->edit_version])->assertSessionHasErrors('stage');
        $payment=['receipt_amount'=>183225,'receipt_date'=>today()->toDateString(),'receipt_method'=>'cash','receipt_confirmed'=>1,'receipt_token'=>(string)Str::uuid()];
        $this->post($url.'/paiements',$payment)->assertSessionHasNoErrors();$this->assertTrue(QuoteFinancials::forQuote($q->fresh())['productionAuthorized']);
        $this->post($url.'/avancement',['stage'=>'in_production','edit_version'=>$q->edit_version])->assertSessionHasNoErrors();
        $this->assertSame('in_production',$q->fresh()->configuration['workflow_stage']);
        $this->post($url.'/avancement',['stage'=>'paid','edit_version'=>$q->fresh()->edit_version])->assertSessionHasErrors('stage');
        $this->post($url.'/paiements',[...$payment,'receipt_token'=>(string)Str::uuid(),'receipt_amount'=>81563])->assertSessionHasErrors('receipt_amount');
    }

    public function test_installment_schedule_is_saved_with_the_quote_and_shown_on_documents(): void
    {
        $this->seed(); $this->login();
        $data=$this->payload();
        // Sans choix d'échéancier (ancien formulaire), le devis garde son acompte unique.
        $old=$this->save($data);
        $this->assertArrayNotHasKey('schedule',$old->configuration['calculation']['financial']);
        $this->assertStringContainsString('name="pricing[schedule]" value="two" checked',$this->get('/admin/devis/'.$old->id.'/formulaire')->assertOk()->getContent());
        // 3 échéances : l'acompte enregistré est la première, et la somme des échéances fait le total.
        $q=$this->save([...$data,'submission_token'=>(string)Str::uuid(),'pricing'=>[...$data['pricing'],'schedule'=>'three']]);
        $financial=$q->configuration['calculation']['financial'];
        $this->assertSame(['50','30','20'],array_column($financial['schedule'],'percent'));
        $this->assertSame($financial['finalTotal'],array_sum(array_column($financial['schedule'],'amount')));
        $this->assertSame($financial['schedule'][0]['amount'],$q->deposit_amount);
        $this->assertSame('three',$q->configuration['calculation']['pricing']['schedule']);
        $html=view('documents.pdf',\App\Services\DocumentViewModel::prepare(app(Documents::class)->quoteData($q)))->render();
        $this->assertStringContainsString('APRÈS INSTALLATION DES RAILS (30 %)',$html);
        $this->assertStringContainsString('Notre engagement.',$html);
        $this->assertStringStartsWith('Échéancier : 50 % à la validation du chantier',CommunicationTemplates::context($q)['conditions_paiement'] ?? '');
        $this->assertStringContainsString('name="pricing[schedule]" value="three" checked',$this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk()->getContent());
        $this->assertStringContainsString('name="pricing[schedule]" value="auto" checked',$this->get('/admin/devis/nouveau')->assertOk()->getContent());
        $this->postJson('/admin/devis/chiffrer',[...$data,'pricing'=>[...$data['pricing'],'schedule'=>'four']])->assertStatus(422)->assertJsonValidationErrors('pricing.schedule');
        // Un agent ne voit ni échéancier ni montant.
        $this->login('agent');
        $agent=$this->get('/admin/devis/nouveau')->assertOk();
        foreach (['pricing[schedule]','Notre engagement','FCFA'] as $secret) $agent->assertDontSee($secret,false);
    }

    public function test_step_three_sewing_counts_the_sheer_only_through_existing_fields(): void
    {
        $this->seed(); $this->login();
        $form=$this->get('/admin/devis/nouveau')->assertOk();
        foreach (['data-sewing="curtain"','data-sewing="sheer"','data-sewing="store"','name="sewing_ui[curtain]" value="manual"','name="zone_ui" value="other"','data-install-count','data-set-coef="1.5"','name="windows[0][costing][sewing][basis]" value="primary"','data-step3'] as $html) $form->assertSee($html,false);
        $data=$this->payload(); $voile=$this->id('voilage-luxe');
        $data['windows'][0]=[...$data['windows'][0],'inherit_defaults'=>0,'sheer_id'=>$voile];
        $data['windows'][0]['costing']['secondary_material']=['option_id'=>$voile,'quantity'=>'4'];
        $total=fn(array $d)=>$this->postJson('/admin/devis/chiffrer',$d)->assertOk()->json('financial.finalTotal');
        $primary=$total($data);
        // Voilage compté avec le rideau : 4 m de plus à la confection (1 000 FCFA / m), pour 2 exemplaires.
        $data['windows'][0]['costing']['sewing']['basis']='all_fabrics';
        $this->assertSame($primary+8000,$total($data));
        // Prix saisi à l'étape 3 : il remplace le tarif par mètre de la confection (9 m × 1 500 × 2 au lieu de 9 m × 1 000 × 2).
        $data['windows'][0]['costing']['sewing']['unit_price']=1500;
        $this->assertSame($primary+8000+9000,$total($data));
        // Les pastilles de l'étape 3 ne sont jamais lues par le serveur.
        $this->assertSame($primary+17000,$total([...$data,'sewing_ui'=>['curtain'=>'none','sheer'=>'manual'],'sewing_ui_price'=>['curtain'=>1],'zone_ui'=>'other']));
        $this->post('/admin/devis',$this->calculated([...$data,'sewing_ui'=>['curtain'=>'manual']]))->assertSessionHasNoErrors();
        $q=Quote::latest('id')->firstOrFail();
        $this->assertSame('all_fabrics',$q->dimensions[0]['costing']['sewing']['basis']);
        $this->assertArrayNotHasKey('sewing_ui',$q->configuration);
    }

    public function test_opening_photos_and_commune_are_saved_only_for_their_quote(): void
    {
        $this->seed(); $owner=$this->login();
        $data=$this->payload(); $data['contact']=[...$data['contact'],'city'=>'Cocody','address'=>'Riviera 3, rue des Jardins'];
        $form=$this->get('/admin/devis/nouveau')->assertOk();
        foreach (['name="contact[city]" required data-commune','<option value="Yopougon"','<option value="Hors Abidjan"','name="contact[address]"','name="windows[0][photo]"','capture="environment"','enctype="multipart/form-data"','data-choice-board','data-supply-board','data-calc-form hidden','name="coefficient_ui[material]" value="2.5"','name="install_ui" value="manual"'] as $html) $form->assertSee($html,false);
        // Les pastilles des étapes 2 et 3 ne sont pas enregistrées : le calcul reste celui des champs de chaque ouverture.
        $plain=$this->postJson('/admin/devis/chiffrer',$data)->json('financial.finalTotal');
        $this->assertSame($plain,$this->postJson('/admin/devis/chiffrer',[...$data,'coefficient_ui'=>['material'=>'3'],'install_ui'=>'manual','install_ui_price'=>1])->json('financial.finalTotal'));
        // Le calcul n'a pas besoin de la photo ; l'enregistrement la range dans l'ouverture.
        $calculated=$this->calculated([...$data,'coefficient_ui'=>['material'=>'3']]); $calculated['windows'][0]['photo']=\Illuminate\Http\UploadedFile::fake()->image('salon.png',400,300);
        $this->post('/admin/devis',$calculated)->assertSessionHasNoErrors();
        $q=Quote::latest('id')->firstOrFail();
        $this->assertSame('Cocody',$q->contact['city']);
        $photo=$q->dimensions[0]['photo_media_id']; $this->assertIsInt($photo); $this->assertArrayNotHasKey('photo_media_id',$q->dimensions[1]);
        $this->get('/admin/devis/'.$q->id.'/photos/'.$photo)->assertOk()->assertHeader('Content-Type','image/png');
        $this->get('/admin/devis/'.$q->id)->assertSee('/admin/devis/'.$q->id.'/photos/'.$photo,false);
        $edit=$this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk()->assertSee('name="windows[0][photo_media_id]" value="'.$photo.'"',false);
        $edit->assertSee('<option value="Cocody" selected>',false);
        // Une ancienne ville hors liste reste proposée.
        $old=$q->replicate(['reference','submission_token']); $old->contact=[...$q->contact,'city'=>'Grand-Bassam']; $old->save();
        $this->get('/admin/devis/'.$old->id.'/formulaire')->assertSee('<option value="Grand-Bassam" selected>',false);
        // Une photo d'un autre devis ou d'un produit n'est jamais reprise, ni lisible depuis ce devis.
        $foreign=\App\Models\MediaAsset::create(['mime'=>'image/png','width'=>1,'height'=>1,'bytes'=>1,'sha256'=>str_repeat('a',64),'payload'=>base64_encode('x')])->id;
        $next=$this->calculated([...$data,'edit_version'=>$q->edit_version]);
        $next['windows'][0]['photo_media_id']=$photo; $next['windows'][1]['photo_media_id']=$foreign;
        $this->put('/admin/devis/'.$q->id.'/formulaire',$next)->assertSessionHasNoErrors();
        $q->refresh();
        $this->assertSame($photo,$q->dimensions[0]['photo_media_id']); $this->assertArrayNotHasKey('photo_media_id',$q->dimensions[1]);
        $this->get('/admin/devis/'.$q->id.'/photos/'.$foreign)->assertNotFound();
        // Retirer la photo la supprime.
        $remove=$this->calculated([...$data,'edit_version'=>$q->edit_version]); $remove['windows'][0]['photo_media_id']=$photo; $remove['windows'][0]['photo_remove']=1;
        $this->put('/admin/devis/'.$q->id.'/formulaire',$remove)->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('photo_media_id',$q->fresh()->dimensions[0]);
        $this->assertNull(\App\Models\MediaAsset::find($photo));
        $bad=$this->calculated([...$data,'submission_token'=>(string)Str::uuid()]); $bad['windows'][0]['photo']=\Illuminate\Http\UploadedFile::fake()->create('notes.pdf',10,'application/pdf');
        $this->post('/admin/devis',$bad)->assertSessionHasErrors('windows.0.photo');
        // Un agent qui n'a pas créé le devis ne lit pas ses photos.
        $kept=$this->calculated([...$data,'submission_token'=>(string)Str::uuid()]); $kept['windows'][0]['photo']=\Illuminate\Http\UploadedFile::fake()->image('o.png',40,30);
        $this->post('/admin/devis',$kept); $other=Quote::latest('id')->firstOrFail();
        $this->login('agent');
        $this->get('/admin/devis/'.$other->id.'/photos/'.$other->dimensions[0]['photo_media_id'])->assertForbidden();
    }
}
