<?php
namespace Tests\Feature;
use App\Models\{Product,Quote,QuoteOption,User};
use App\Services\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class TechnicalQuotesTest extends TestCase
{
    use RefreshDatabase;
    private function login(string $role): User {
        auth()->logout(); session()->flush();
        $u=User::create(['username'=>Str::uuid(),'email'=>Str::random(8).'@example.invalid','name'=>'Test '.$role,'password'=>'Test123!Password','role'=>$role,'must_change_password'=>false]);
        $this->actingAs($u); return $u;
    }
    private function id(string $code): int { return QuoteOption::where('code',$code)->value('id'); }
    private function data(): array {
        $s=['category'=>'rideau','motorized'=>0,'curtain_id'=>$this->id('occultant-100'),'sheer_id'=>$this->id('voilage-transparent'),'store_id'=>null,'finish_id'=>$this->id('wave-manuel')];
        return ['submission_token'=>(string)Str::uuid(),'edit_version'=>0,'defaults'=>$s,'windows'=>[[...$s,'inherit_defaults'=>1,'label'=>'Salon','width'=>200,'height'=>250,'quantity'=>1,'costing'=>['zone'=>'abidjan','material_quantity'=>4,'secondary_material'=>['quantity'=>3]]]],
            'contact'=>['lastName'=>'Client test','phone'=>'+2250700000000','address'=>'Abidjan'], 'quote_date'=>today()->toDateString(),'valid_until'=>today()->addDays(30)->toDateString(),'field_notes'=>'Prise de mesures effectuée'];
    }
    public function test_products_are_retired_and_not_recreated_while_custom_products_and_archives_survive(): void {
        $this->seed(); $this->assertDatabaseCount('products',0);
        $this->seedProducts(); $old=Product::firstOrFail();
        Product::create(['reference'=>'CUSTOM-NEW','details'=>['name'=>'Nouveau produit','type'=>'store']]);
        $q=Quote::create(['reference'=>Str::uuid(),'type'=>'rideau','contact'=>[],'product'=>['model'=>$old->details['name']],'dimensions'=>[]]); $before=$q->fresh()->getAttributes();
        $m=require database_path('migrations/2026_09_29_000010_separate_quote_fabrics.php');$m->retireProducts();$this->seed();
        $this->assertSame(14,Product::onlyTrashed()->count());$this->assertSame(1,Product::count());$this->assertSame($before,$q->fresh()->getAttributes());
        $this->assertSame(8,QuoteOption::where('kind','material')->count());
    }
    public function test_agent_saves_separate_curtains_and_sheers_without_email_or_prices(): void {
        $this->seed(); $this->login('agent'); $data=$this->agentPayload($this->data());
        $html=$this->get('/admin/devis/nouveau')->assertOk()->assertSee('Voilages')->assertSee('facultatif')->getContent();
        foreach (['unit_price','calculation_token','name="lines','FCFA','[product_id]'] as $secret) $this->assertStringNotContainsString($secret,$html);
        $this->post('/admin/devis',$data)->assertSessionHasNoErrors();$q=Quote::firstOrFail();
        $this->assertSame('Rideaux et voilages',$q->product['model']);
        $this->assertSame('rideau_voilage',$q->dimensions[0]['project_type']);$this->assertArrayNotHasKey('costing',$q->dimensions[0]);
        $this->assertNull($q->customer->email);$this->assertTrue($q->configuration['pricing_review_required']);
        $this->get('/admin/devis/'.$q->id)->assertOk()->assertSee('Rideaux et voilages')->assertDontSee('FCFA');
    }
    public function test_agent_cannot_read_prices_or_write_financial_fields_and_edits_preserve_admin_amounts(): void {
        $this->seed();$this->login('agent');$data=$this->agentPayload($this->data());$this->post('/admin/devis',$data)->assertSessionHasNoErrors();$q=Quote::firstOrFail();
        $lines=[['description'=>'Admin chiffrage confidentiel','quantity'=>1,'unit_price'=>9876543]];
        $q->update(['lines'=>$lines,'tax_basis_points'=>1800,'deposit_amount'=>1234567,'internal_notes'=>'ADMIN-PRIVATE-NOTE']);
        $lines=$q->fresh()->lines; // Compare the database snapshot; MySQL canonicalizes JSON object key order.
        foreach (['','/formulaire'] as $path) {
            $html=$this->get('/admin/devis/'.$q->id.$path)->assertOk()->getContent();
            foreach (['9876543','1234567','ADMIN-PRIVATE-NOTE','Admin chiffrage confidentiel','unit_price'] as $secret) $this->assertStringNotContainsString($secret,$html);
        }
        $this->postJson('/admin/devis/chiffrer',$data)->assertForbidden();$this->get('/admin/devis/'.$q->id.'/pdf')->assertForbidden();
        $this->put('/admin/devis/'.$q->id,['lines'=>$lines])->assertForbidden();$this->post('/admin/devis/'.$q->id.'/paiements',['receipt_amount'=>1])->assertForbidden();
        $this->put('/admin/devis/'.$q->id.'/formulaire',[...$data,'lines'=>$lines])->assertSessionHasErrors('lines');
        $data['windows'][0]['width']=210;
        $this->put('/admin/devis/'.$q->id.'/formulaire',$data)->assertSessionHasNoErrors();$q->refresh();
        $this->assertSame($lines,$q->lines);$this->assertSame(1234567,$q->deposit_amount);$this->assertSame(1800,$q->tax_basis_points);$this->assertSame('ADMIN-PRIVATE-NOTE',$q->internal_notes);
        $this->login('admin');
        $this->put('/admin/devis/'.$q->id,['lines'=>$lines,'tax_rate'=>18,'valid_until'=>today()->addDays(30)->toDateString(),'status'=>'approved'])->assertSessionHasErrors('status');
    }
    public function test_admin_can_calculate_and_save_the_two_fabrics_without_a_product(): void {
        $this->seed();$this->login('admin');$data=$this->data();
        $r=$this->postJson('/admin/devis/chiffrer',$data)->assertOk()->json();
        $this->assertSame(60000,Pricing::totals($r['lines'],0)['total']);
        $data += ['lines'=>$r['lines'],'calculation_token'=>$r['token'],'use_catalogue'=>1,'payment_mode'=>'full','deposit_amount'=>0,'tax_rate'=>0,'receipt_token'=>(string)Str::uuid()];
        $this->post('/admin/devis',$data)->assertSessionHasNoErrors(); $q=Quote::firstOrFail();
        $this->assertFalse($q->configuration['pricing_review_required']);$this->assertSame(2,count($q->configuration['calculation']['items']));
        $this->get('/admin/devis/'.$q->id.'/pdf')->assertOk();
    }
    public function test_agent_records_project_type_without_selecting_materials(): void {
        $this->seed();$this->login('agent');$data=$this->agentPayload($this->data());$data['defaults']['project_type']='voilage';
        $this->post('/admin/devis',$data)->assertSessionHasNoErrors();$this->assertSame('Voilages',Quote::first()->product['model']);
        $bad=$data;$bad['defaults']['material_id']=$this->id('voilage-luxe');
        $this->post('/admin/devis',$bad)->assertSessionHasErrors('defaults.material_id');
    }
    public function test_public_email_is_optional_and_anonymous_clients_are_not_merged(): void {
        $this->seed();
        $model=Product::create(['reference'=>'TEST-RIDEAU','published'=>true,'details'=>['name'=>'Rideau de test','type'=>'rideau','available'=>true]]);
        $data=['contact'=>['civility'=>'mme','lastName'=>'Test','firstName'=>'Awa','phone'=>'+2250700000000','address'=>'Abidjan test','city'=>'Abidjan'],
            'product'=>['model'=>'product-'.$model->id,'fabric'=>'occultant-100','control'=>'manuelle','finish'=>'wave','color'=>'beige'],
            'dimensions'=>[['label'=>'Salon','width'=>200,'height'=>250,'quantity'=>1]],'acceptTerms'=>true];
        $this->postJson('/api/quotes/curtains',$data)->assertCreated();$data['contact']['lastName']='Second';$this->postJson('/api/quotes/curtains',$data)->assertCreated();
        $this->assertDatabaseCount('contacts',2);
        $data['contact']['email']='invalid';$this->postJson('/api/quotes/curtains',$data)->assertUnprocessable()->assertJsonValidationErrors('contact.email');
    }
}
