<?php
namespace Tests\Feature;
use App\Models\{Product,Quote,QuoteOption,User};
use App\Services\QuotePhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class QuotePhoneTest extends TestCase
{
    use RefreshDatabase;
    public function test_country_prefix_normalization_preserves_ivorian_zero_and_complete_numbers(): void {
        foreach([['07 12 44 93 19','+225','+2250712449319'],['06 12 34 56 78','+33','+33612345678'],['+33 6 12 34 56 78','+225','+33612345678'],['00225 07 12 44 93 19','+225','+2250712449319'],['','+225','']] as [$number,$prefix,$expected])$this->assertSame($expected,QuotePhone::normalize($number,$prefix));
        $this->assertSame(['prefix'=>'+225','number'=>'0712449319'],QuotePhone::split('+2250712449319'));
        $this->assertSame(['prefix'=>'+33','number'=>'612345678'],QuotePhone::split('+33612345678'));
    }
    public function test_public_curtain_quote_keeps_motorization_and_normalizes_phone_in_archive(): void {
        $this->seed();$product=Product::create(['reference'=>'MOTOR-TEST','published'=>true,'details'=>['type'=>'rideau','name'=>'Rideau','available'=>true]]);
        $data=['contact'=>['civility'=>'mme','firstName'=>'Client','lastName'=>'Test','phoneCountryCode'=>'+225','phone'=>'07 12 44 93 19','address'=>'Adresse de test','city'=>'Abidjan'],
            'product'=>['model'=>'product-'.$product->id,'fabric'=>'occultant-100','color'=>'beige','finish'=>'wave','control'=>'motorisee'],'dimensions'=>[['label'=>'Ouverture','width'=>150,'height'=>250,'quantity'=>1]],'acceptTerms'=>true];
        $this->postJson('/api/quotes/curtains',$data)->assertCreated();$quote=Quote::firstOrFail();$this->assertSame('motorisee',$quote->product['control']);$this->assertSame('+2250712449319',$quote->contact['phone']);$this->assertSame('+2250712449319',$quote->customer->phone);
        $data['contact']['phoneCountryCode']='+000';$this->postJson('/api/quotes/curtains',$data)->assertUnprocessable()->assertJsonValidationErrors('contact.phoneCountryCode');
        $data['contact']['phoneCountryCode']='+33';unset($data['product']['control']);$this->postJson('/api/quotes/curtains',$data)->assertUnprocessable()->assertJsonValidationErrors('product.control');
        $this->assertDatabaseCount('quotes',1);
        $admin=User::create(['username'=>'PhoneAdmin','name'=>'Admin','email'=>'phone-admin@example.invalid','password'=>'Example123!','role'=>'admin','must_change_password'=>false]);
        $html=$this->actingAs($admin)->get('/admin/devis/'.$quote->id.'/formulaire')->assertOk()->getContent();
        $this->assertStringContainsString('name="defaults[motorized]" data-value="1"',$html);
    }
    public function test_staff_forms_include_prefix_and_agent_saves_a_french_number_without_duplicate_prefix(): void {
        $this->seed();$agent=User::create(['username'=>'PhoneAgent','name'=>'Agent','email'=>'phone@example.invalid','password'=>'Example123!','role'=>'agent','must_change_password'=>false]);$this->actingAs($agent);
        $this->get('/admin/devis/nouveau')->assertOk()->assertSee('contact[phoneCountryCode]',false)->assertSee('Motorisation');
        $selection=['project_type'=>'rideau','motorized'=>1,'finish_id'=>QuoteOption::where('code','wave-electric')->value('id'),'zone'=>'abidjan'];
        $data=['submission_token'=>(string)Str::uuid(),'edit_version'=>0,'contact'=>['lastName'=>'Client','phoneCountryCode'=>'+33','phone'=>'06 12 34 56 78','address'=>'Adresse de test'],'defaults'=>$selection,'windows'=>[['label'=>'Salon','width'=>150,'height'=>230,'inherit_defaults'=>1]]];
        $this->post('/admin/devis',$data)->assertSessionHasNoErrors();$q=Quote::firstOrFail();$this->assertSame('+33612345678',$q->contact['phone']);$this->assertTrue($q->dimensions[0]['motorized']);
        $this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk()->assertSee('value="+33"',false)->assertSee('value="612345678"',false);
        $data['edit_version']=0;$data['contact']['phone']='+33 6 12 34 56 78';$this->put('/admin/devis/'.$q->id.'/formulaire',$data)->assertSessionHasNoErrors();$this->assertSame('+33612345678',$q->fresh()->contact['phone']);
        $agent->update(['role'=>'admin']);$this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk()->assertSee('contact[phoneCountryCode]',false)->assertSee('value="+33"',false);
    }
}
