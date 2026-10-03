<?php
namespace Tests\Feature;
use App\Models\{User,Quote,Product,QuoteOption,SiteContent};
use App\Services\PublicQuoteChoices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;
class QuoteJourneyTest extends TestCase
{
    use RefreshDatabase;
    private function user(string $role): User {return User::create(['username'=>Str::uuid(),'name'=>'Test','email'=>Str::uuid().'@example.invalid','password'=>'Example123!','role'=>$role,'must_change_password'=>false]);}
    public function test_public_curtain_requests_allow_curtain_sheer_or_both_and_reject_technical_options(): void {
        $this->seed();$p=Product::create(['reference'=>'CURTAIN-TEST','published'=>true,'details'=>['type'=>'rideau','name'=>'Rideau','available'=>true]]);
        $data=['contact'=>['civility'=>'mme','firstName'=>'Awa','lastName'=>'Test','phone'=>'+2250700000000','address'=>'Adresse de test','city'=>'Abidjan'],
            'product'=>['model'=>'product-'.$p->id,'fabric'=>'occultant-100','sheer'=>'voilage-transparent','color'=>'beige','control'=>'manuelle','finish'=>'wave'],'dimensions'=>[['label'=>'Ouverture 1','width'=>120,'height'=>220,'quantity'=>1]],'acceptTerms'=>true];
        foreach([['occultant-100','voilage-transparent'],['occultant-80',''],['','voilage-luxe']] as [$fabric,$sheer]){$data['product']['fabric']=$fabric;$data['product']['sheer']=$sheer;$this->postJson('/api/quotes/curtains',$data)->assertCreated();}
        $this->assertStringContainsString(' + ',Quote::first()->product['fabric']);$this->assertSame('Wave',Quote::first()->product['finish']);
        $data['product']['sheer']='';$this->postJson('/api/quotes/curtains',$data)->assertUnprocessable()->assertJsonValidationErrors(['product.fabric','product.sheer']);
        $data['product']['fabric']='voilage-luxe';$this->postJson('/api/quotes/curtains',$data)->assertUnprocessable()->assertJsonValidationErrors('product.fabric');
        $data['product']['fabric']='occultant-100';$data['product']['finish']='wave-manuel';$this->postJson('/api/quotes/curtains',$data)->assertUnprocessable()->assertJsonValidationErrors('product.finish');
        $this->assertDatabaseCount('quotes',3);
    }
    public function test_admin_edits_material_image_and_public_finish_cards_and_messages(): void {
        $this->seed();$this->withoutVite();$this->actingAs($this->user('admin'));$o=QuoteOption::where('code','occultant-100')->firstOrFail();
        $data=['label'=>$o->label,'kind'=>'material','category'=>'rideau','fabric_group'=>'rideau','motorization'=>'any','active'=>1,'public_choice'=>1,'image'=>UploadedFile::fake()->image('cloth.png',320,180),'image_alt'=>'Tissu réel'];
        $this->put('/admin/options-devis/'.$o->id,$data)->assertSessionHasNoErrors();$o->refresh();$this->assertNotNull($o->media_asset_id);
        $option=collect(PublicQuoteChoices::data())->firstWhere('id','occultant-100');$this->get($option['imageUrl'])->assertOk()->assertHeader('Content-Type','image/png');
        $card=SiteContent::where('slot','finish-wave')->firstOrFail();$message=SiteContent::where('slot','quote-message-rideau')->firstOrFail();
        foreach([$card,$message] as $c)$this->put('/admin/contents/'.$c->id,['title'=>'Titre personnalisé','description'=>'Description personnalisée','published'=>1,'position'=>0])->assertSessionHasNoErrors();
        $this->assertSame('Titre personnalisé',PublicQuoteChoices::finishes()[0]['label']);$this->assertSame('Description personnalisée',PublicQuoteChoices::messages()['rideau']['text']);
        $o->update(['public_choice'=>false]);$this->get($option['imageUrl'])->assertNotFound();
        auth()->logout();session()->flush();$this->actingAs($this->user('agent'));$this->put('/admin/options-devis/'.$o->id,$data)->assertForbidden();
    }
    public function test_agent_restricted_form_saves_field_notes_and_preserves_admin_work_on_edit(): void {
        $this->seed();$agent=$this->user('agent');$this->actingAs($agent);$finish=QuoteOption::where('code','wave-manuel')->value('id');
        $selection=['project_type'=>'rideau_voilage','motorized'=>0,'finish_id'=>$finish,'zone'=>'other'];
        $data=['submission_token'=>(string)Str::uuid(),'edit_version'=>0,'contact'=>['lastName'=>'Client Test','phone'=>'+2250700000000','address'=>'Abidjan'],'defaults'=>$selection,'windows'=>[['label'=>'Salon','width'=>'140,5','height'=>220,'inherit_defaults'=>1,'notes'=>'Description conservée']],'field_notes'=>'Observation terrain'];
        $html=$this->get('/admin/devis/nouveau')->assertOk()->getContent();foreach(['name="quote_date"','name="agent_id"','material_quantity','data-fabric-list','data-finish-image','unit_price'] as $field)$this->assertStringNotContainsString($field,$html);
        $this->post('/admin/devis',$data)->assertSessionHasNoErrors();$q=Quote::firstOrFail();$this->assertSame($agent->id,$q->created_by);$this->assertEquals(140.5,$q->dimensions[0]['width']);$this->assertSame('other',$q->dimensions[0]['zone']);
        $dimensions=$q->dimensions;$dimensions[0]['costing']=['zone'=>'other','material_quantity'=>8,'support'=>['option_id'=>22,'quantity'=>4]];$dimensions[0]['material_name']='Choix Admin';
        $q->update(['dimensions'=>$dimensions,'quote_date'=>'2026-10-03','valid_until'=>'2026-11-03','lines'=>[['description'=>'Chiffrage','quantity'=>1,'unit_price'=>70000]]]);$before=$q->fresh();
        $data['windows'][0]['width']=155;$this->put('/admin/devis/'.$q->id.'/formulaire',$data)->assertSessionHasNoErrors();$q->refresh();
        $this->assertSame($before->lines,$q->lines);$this->assertSame('2026-11-03',$q->valid_until);$this->assertEquals(8,$q->dimensions[0]['costing']['material_quantity']);$this->assertSame('Choix Admin',$q->dimensions[0]['material_name']);$this->assertSame('Observation terrain',$q->configuration['field_notes']);
        foreach(['quote_date'=>'2026-12-01','agent_id'=>999,'lines'=>[['description'=>'Intrusion','quantity'=>1,'unit_price'=>1]]] as $key=>$value)$this->post('/admin/devis',[...$data,'submission_token'=>(string)Str::uuid(),$key=>$value])->assertSessionHasErrors($key);
        $data['windows'][0]['costing']=['material_quantity'=>99];$this->post('/admin/devis',$data)->assertSessionHasErrors('windows.0.costing');
        $this->assertDatabaseCount('quotes',1);
    }
}
