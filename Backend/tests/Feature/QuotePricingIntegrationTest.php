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
        $f=QuoteFinancials::forQuote($q);$this->assertSame(271875,$f['total']);$this->assertSame(190313,$q->deposit_amount);$this->assertSame(81562,$f['balance']);
        $this->get('/admin/devis/'.$q->id)->assertOk()->assertSee('271 875')->assertSee('190 313');
        $this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk()->assertSee('271 875');
        $context=CommunicationTemplates::context($q);$this->assertSame('271 875',$context['montant']);$this->assertSame('81 562',$context['solde']);
        $doc=app(Documents::class)->quoteData($q);$html=view('staff.document',QuoteFinancials::document($doc))->render();
        $this->assertStringContainsString('271 875',$html);$this->assertStringContainsString('solde de 30 %',$html);$this->assertStringNotContainsString('80 %',$html);
        $this->get('/admin/devis/'.$q->id.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        QuoteOption::where('code','occultant-80')->update(['unit_price'=>99000]);$this->assertSame(271875,QuoteFinancials::forQuote($q->fresh())['total']);
        $this->put('/admin/devis/'.$q->id,['lines'=>$q->lines,'tax_rate'=>0,'valid_until'=>$q->valid_until,'status'=>'accepted'])->assertSessionHasNoErrors();
        $this->post('/admin/devis/'.$q->id.'/facture',['due_date'=>today()->addDays(10)->toDateString()])->assertSessionHasNoErrors();
        $invoice=Invoice::firstOrFail();$this->assertSame(271875,QuoteFinancials::forSnapshot($invoice->snapshot)['total']);
        $this->assertSame('271 875',CommunicationTemplates::context($invoice)['montant']);
        $this->get('/admin/factures/'.$invoice->id)->assertOk()->assertSee('190 313');
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
    public function test_catalogue_global_fee_is_frozen_until_explicit_recalculation(): void
    {
        $this->seed();$this->login();$data=$this->payload();
        $option=QuoteOption::create(['code'=>'global-fee-test','label'=>'Pose et livraison','kind'=>'combined','category'=>'rideau','unit'=>'flat','unit_price'=>30000,'active'=>true,'motorization'=>'any']);
        $data['pricing']['fees']['installation_delivery']['option_id']=$option->id;$data['pricing']['fees']['installation_delivery']['unit_price']=null;
        $signed=$this->calculated($data);$option->update(['unit_price'=>50000]);
        $this->post('/admin/devis',$signed)->assertSessionHasNoErrors();$q=Quote::firstOrFail();
        $this->assertSame(271875,QuoteFinancials::forQuote($q)['total']);
        $this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk();
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();$this->assertSame(291875,$r->json('financial.total'));
        $data['pricing']['fees']['installation_delivery']['unit_price']=0;
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();$this->assertSame(241875,$r->json('financial.total'));
    }
    public function test_global_pose_cannot_duplicate_per_opening_pose_and_zero_fees_are_allowed(): void
    {
        $this->seed();$this->login();$data=$this->payload();
        $data['windows'][0]['costing']['installation']=['option_id'=>$this->id('pose-rideaux'),'quantity'=>1,'unit_price'=>1000];
        $this->postJson('/admin/devis/chiffrer',$data)->assertUnprocessable()->assertJsonValidationErrors('windows.0.costing.installation.option_id');
        $data=$this->payload();$data['pricing']['fees']['delivery']['enabled']=1;
        $this->postJson('/admin/devis/chiffrer',$data)->assertUnprocessable()->assertJsonValidationErrors('pricing.fees');
        $data=$this->payload();$data['pricing']['fees']['installation_delivery']['unit_price']=0;
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();$this->assertSame(241875,$r->json('financial.total'));$this->assertSame([],$r->json('pending'));
    }
    public function test_motorized_curtain_accepts_extra_manual_rail_heavy_voile_and_fixed_confection(): void
    {
        $this->seed();$this->login();$data=$this->payload();$data['defaults']['motorized']=1;$data['defaults']['finish_id']=$this->id('wave-electric');$data['defaults']['sheer_id']=$this->id('voilage-lourd');
        $data['windows']=[$data['windows'][0]];$data['windows'][0]['quantity']=1;
        $cost=&$data['windows'][0]['costing'];$cost['support']=['option_id'=>$this->id('rail-wave-electric')];unset($cost['tape']);
        $cost['secondary_material']=['quantity'=>4];$cost['extra_support']=['option_id'=>$this->id('rail-simple')];
        $cost['motor']=['option_id'=>$this->id('kit-moteur-rideau'),'quantity'=>1];$cost['sewing']=['option_id'=>$this->id('confection'),'mode'=>'fixed','unit_price'=>25000];
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();$this->assertSame(298000,$r->json('financial.total'));
        $q=$this->save($data);$this->assertSame(298000,QuoteFinancials::forQuote($q)['total']);
    }
    public function test_agents_cannot_see_or_submit_any_new_prices(): void
    {
        $this->seed();$this->login();$q=$this->save($this->payload());$agent=$this->login('agent');$q->update(['agent_id'=>$agent->id]);
        foreach (['','/formulaire'] as $path) {
            $html=$this->get('/admin/devis/'.$q->id.$path)->assertOk()->getContent();
            foreach (['unit_price','271875','271 875','190313','discount_type','F CFA'] as $secret) $this->assertStringNotContainsString($secret,$html);
        }
        $this->postJson('/admin/devis/chiffrer',$this->payload())->assertForbidden();
        $this->post('/admin/devis/'.$q->id.'/avancement',['stage'=>'in_production','edit_version'=>0])->assertForbidden();
        $data=$this->payload();unset($data['pricing'],$data['tax_rate'],$data['payment_mode'],$data['deposit_amount']);
        foreach (['material_unit_price','finish_unit_price'] as $key) {
            $bad=$data;$bad['windows'][0]['costing'][$key]=1;$this->put('/admin/devis/'.$q->id.'/formulaire',$bad)->assertSessionHasErrors('windows.0.costing.'.$key);
        }
        $bad=$data;$bad['windows'][0]['costing']['support']['unit_price']=1;$this->put('/admin/devis/'.$q->id.'/formulaire',$bad)->assertSessionHasErrors('windows.0.costing.support.unit_price');
    }
    public function test_production_is_blocked_until_confirmed_deposit_and_overpayments_are_rejected(): void
    {
        $this->seed();$this->login();$data=$this->payload();$q=$this->save($data);$q->update(['status'=>'accepted']);
        $url='/admin/devis/'.$q->id;
        $this->post($url.'/avancement',['stage'=>'in_production','edit_version'=>$q->edit_version])->assertSessionHasErrors('stage');
        $payment=['receipt_amount'=>190313,'receipt_date'=>today()->toDateString(),'receipt_method'=>'cash','receipt_confirmed'=>1,'receipt_token'=>(string)Str::uuid()];
        $this->post($url.'/paiements',$payment)->assertSessionHasNoErrors();$this->assertTrue(QuoteFinancials::forQuote($q->fresh())['productionAuthorized']);
        $this->post($url.'/avancement',['stage'=>'in_production','edit_version'=>$q->edit_version])->assertSessionHasNoErrors();
        $this->assertSame('in_production',$q->fresh()->configuration['workflow_stage']);
        $this->post($url.'/avancement',['stage'=>'paid','edit_version'=>$q->fresh()->edit_version])->assertSessionHasErrors('stage');
        $this->post($url.'/paiements',[...$payment,'receipt_token'=>(string)Str::uuid(),'receipt_amount'=>81563])->assertSessionHasErrors('receipt_amount');
    }
}
