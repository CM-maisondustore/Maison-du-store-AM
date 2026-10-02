<?php
namespace Tests\Feature;
use App\Models\{Quote,QuoteOption,Product,User};
use App\Services\{QuoteCosting,Pricing,Documents};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;
class QuoteCostingTest extends TestCase
{
    use RefreshDatabase;
    private function login(string $role='admin'): void {
        $this->actingAs(User::create(['username'=>Str::uuid(),'email'=>Str::random(8).'@example.invalid','name'=>'Test','password'=>'Test123!Password','role'=>$role,'must_change_password'=>false]));
    }
    private function id(string $code): int { return QuoteOption::where('code',$code)->value('id'); }
    private function data(string $category='rideau'): array {
        $selection=['category'=>$category,'product_id'=>Product::where('details->type',$category)->value('id'),'material_id'=>$this->id($category==='rideau' ? 'occultant-100' : 'store-lourd'),'finish_id'=>$this->id($category==='rideau' ? 'wave-manuel' : 'store-standard'),'motorized'=>0];
        $cost=['zone'=>'abidjan','material_quantity'=>4.5,'installation'=>['option_id'=>$this->id($category==='rideau' ? 'pose-rideaux' : 'pose-store-abidjan'),'quantity'=>1,'unit_price'=>null]];
        if ($category==='rideau') $cost += ['support'=>['option_id'=>$this->id('rail-wave-manuel'),'quantity'=>2], 'tape'=>['option_id'=>$this->id('reflete-wave'),'quantity'=>4.5], 'hooks'=>['option_id'=>$this->id('accroches-100'),'quantity'=>1],'sewing'=>['option_id'=>$this->id('confection'),'quantity'=>4.5]];
        return ['defaults'=>$selection,'windows'=>[[...$selection,'label'=>'Salon','width'=>200,'height'=>250,'quantity'=>2,'inherit_defaults'=>1,'costing'=>$cost]]];
    }
    public function test_all_rates_are_seeded_without_overwriting_admin_changes(): void {
        $this->seed();
        $this->assertSame(27,QuoteOption::whereNotNull('unit_price')->count());
        $o=QuoteOption::where('code','voilage-luxe')->firstOrFail();
        $this->assertSame(14000,$o->unit_price); $o->update(['unit_price'=>15000,'active'=>false]);
        $this->seed(); $this->assertSame(15000,$o->fresh()->unit_price); $this->assertFalse($o->fresh()->active);
        $this->assertNull(QuoteOption::where('code','pose-rideaux')->first()->unit_price);
    }
    public function test_curtain_manual_meters_and_full_hook_lots_produce_a_provisional_total(): void {
        $this->seed(); $this->login();
        $response=$this->postJson('/admin/devis/chiffrer',$this->data())->assertOk();
        // One opening: 54000 fabric + 14000 rail + 6750 tape + 2000 hooks + 4500 sewing.
        $this->assertSame(162500,Pricing::totals($response->json('lines'),0)['total']);
        $this->assertNotEmpty($response->json('pending')); $this->assertDatabaseCount('quotes',0);
        $bad=$this->data(); $bad['windows'][0]['costing']['hooks']['quantity']=0.5;
        $this->postJson('/admin/devis/chiffrer',$bad)->assertUnprocessable()->assertJsonValidationErrors('windows.0.costing.hooks.quantity');
        $bad=$this->data(); $bad['windows'][0]['costing']['material_quantity']=null;
        $this->postJson('/admin/devis/chiffrer',$bad)->assertUnprocessable()->assertJsonValidationErrors('windows.0.costing.material_quantity');
    }
    public function test_store_area_motor_and_abidjan_installation_and_other_zone(): void {
        $this->seed(); $this->login(); $data=$this->data('store');
        $data['defaults']['motorized']=1;
        $data['windows'][0]['costing']['motor']=['option_id'=>$this->id('kit-moteur'),'quantity'=>1];
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();
        $this->assertSame(460000,Pricing::totals($r->json('lines'),0)['total']);
        // Old unpriced finishes are deliberately incomplete until assigned an explicit tariff.
        QuoteOption::where('code','standard')->update(['unit_price'=>0]);
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk(); $this->assertSame([],$r->json('pending'));
        $data['windows'][0]['costing']['zone']='other';
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk(); $this->assertNotEmpty($r->json('pending'));
        $this->assertSame(450000,Pricing::totals($r->json('lines'),0)['total']);
    }
    public function test_a_second_curtain_layer_is_costed_without_duplicating_hardware_or_openings(): void {
        $this->seed(); $this->login(); $data=$this->data();
        $data['windows'][0]['costing']['secondary_material']=['option_id'=>$this->id('voilage-transparent'),'quantity'=>4];
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk();
        $this->assertSame(194500,Pricing::totals($r->json('lines'),0)['total']);
    }
    public function test_incompatible_hardware_inactive_options_and_missing_motors_are_rejected(): void {
        $this->seed(); $this->login(); $data=$this->data();
        $data['windows'][0]['costing']['support']['option_id']=$this->id('barre-simple');
        $this->postJson('/admin/devis/chiffrer',$data)->assertUnprocessable()->assertJsonValidationErrors('windows.0.costing.support.option_id');
        $data=$this->data('store'); $data['defaults']['motorized']=1;
        $this->postJson('/admin/devis/chiffrer',$data)->assertUnprocessable()->assertJsonValidationErrors('windows.0.costing.motor.option_id');
        QuoteOption::where('code','occultant-100')->update(['active'=>false]);
        $this->postJson('/admin/devis/chiffrer',$this->data())->assertUnprocessable();
    }
    public function test_quote_persists_tariffs_allows_line_edits_and_requires_pose_before_approval(): void {
        $this->seed(); $this->login(); $data=$this->data();
        $result=$this->postJson('/admin/devis/chiffrer',$data)->assertOk()->json();
        $data += ['receipt_token'=>(string)Str::uuid(),'receipt_amount'=>0,'use_catalogue'=>1,'calculation_token'=>$result['token'],'lines'=>$result['lines'],'tax_rate'=>0,'submission_token'=>(string)Str::uuid(),'edit_version'=>0,
            'contact'=>['lastName'=>'Test','phone'=>'+2250700000000','address'=>'Test Abidjan'], 'quote_date'=>today()->toDateString(),'valid_until'=>today()->addDays(30)->toDateString(),'payment_mode'=>'full','deposit_amount'=>0];
        $data['lines'][0]['unit_price']=53000; // Staff remain free to negotiate the sales lines.
        $this->post('/admin/devis',$data)->assertSessionHasNoErrors(); $q=Quote::firstOrFail();
        $this->assertSame(12000,$q->configuration['calculation']['items'][0]['unit_price']);
        $this->assertSame(53000,$q->lines[0]['unit_price']);
        $this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk()->assertSee('4.5');
        $this->get('/admin/devis/'.$q->id)->assertOk()->assertSee('Devis incomplet');
        $this->assertNotEmpty(app(Documents::class)->quoteData($q)['pending_costs']);
        $this->put('/admin/devis/'.$q->id,['lines'=>$q->lines,'tax_rate'=>0,'valid_until'=>$data['valid_until'],'status'=>'approved'])->assertSessionHasErrors('status');
        QuoteOption::where('code','occultant-100')->update(['unit_price'=>15000]);
        $this->assertSame(12000,$q->fresh()->configuration['calculation']['items'][0]['unit_price']);
        $data['windows'][0]['costing']['installation']['unit_price']=7000;
        $result=$this->postJson('/admin/devis/chiffrer',$data)->assertOk()->json();
        $data['lines']=$result['lines']; $data['calculation_token']=$result['token'];
        $this->put('/admin/devis/'.$q->id.'/formulaire',$data)->assertSessionHasNoErrors();
        $this->put('/admin/devis/'.$q->id,['lines'=>$result['lines'],'tax_rate'=>0,'valid_until'=>$data['valid_until'],'status'=>'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved',$q->fresh()->status);
    }
    public function test_signed_calculation_rejects_changed_measurements_or_tampering(): void {
        $this->seed(); $data=$this->data(); $calc=QuoteCosting::calculate(new Request($data));
        $r=new Request([...$data,'use_catalogue'=>1,'calculation_token'=>QuoteCosting::token($calc)]);
        $this->assertSame(12000,QuoteCosting::saved($r)['items'][0]['unit_price']);
        $data['windows'][0]['width']=300;
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        QuoteCosting::saved(new Request([...$data,'use_catalogue'=>1,'calculation_token'=>QuoteCosting::token($calc)]));
    }
    public function test_public_choices_follow_catalogue_without_exposing_costs_and_are_archived(): void {
        $this->seed(); $this->seedProducts(); $this->withoutVite();
        $choices=\App\Services\PublicQuoteChoices::data();
        $this->assertContains('occultant-100',array_column($choices,'id'));
        $this->assertArrayNotHasKey('unit_price',$choices[0]);
        $data=['contact'=>['civility'=>'mme','firstName'=>'Awa','lastName'=>'Test','email'=>'test@example.invalid','phone'=>'+2250700000000','address'=>'Rue de test','city'=>'Abidjan'],
            'product'=>['model'=>'product-'.\App\Models\Product::where('details->type','rideau')->firstOrFail()->id,'fabric'=>'occultant-100','finish'=>'wave-manuel','hanging'=>'rail-wave-manuel','pattern'=>'uni','color'=>'beige'],
            'dimensions'=>[['label'=>'Salon','width'=>200,'height'=>250,'quantity'=>1]],'acceptTerms'=>true];
        $this->postJson('/api/quotes/curtains',$data)->assertCreated();
        $q=Quote::firstOrFail(); $this->assertSame('Tissu occultant 100 %',$q->product['fabric']);
        QuoteOption::where('code','occultant-100')->update(['label'=>'Nouveau nom','public_choice'=>false]);
        $this->assertSame('Tissu occultant 100 %',$q->fresh()->product['fabric']);
        $this->postJson('/api/quotes/curtains',$data)->assertUnprocessable()->assertJsonValidationErrors('product.fabric');
        $data['product']['fabric']='voilage-luxe'; $data['product']['hanging']='barre-simple';
        $this->postJson('/api/quotes/curtains',$data)->assertUnprocessable()->assertJsonValidationErrors('product.hanging');
    }
    public function test_only_admin_can_edit_costs_and_calculate(): void {
        $this->seed(); $this->login(); $o=QuoteOption::where('code','rail-wave-manuel')->firstOrFail();
        $data=['label'=>$o->label,'kind'=>$o->kind,'category'=>$o->category,'motorization'=>'no','active'=>1,'unit_price'=>8000,'unit'=>'m','finish_codes'=>['wave-manuel']];
        $this->get('/admin/options-devis')->assertOk()->assertSee('Prix unitaire');
        $this->put('/admin/options-devis/'.$o->id,$data)->assertSessionHasNoErrors(); $this->assertSame(8000,$o->fresh()->unit_price);
        auth()->logout(); session()->flush(); $this->login('agent');
        $this->put('/admin/options-devis/'.$o->id,$data)->assertForbidden();
        $this->postJson('/admin/devis/chiffrer',$this->data())->assertForbidden();
    }
}
