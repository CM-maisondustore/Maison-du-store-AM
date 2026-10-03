<?php
namespace Tests\Feature;
use App\Models\{Product,QuoteOption,Quote,User,SiteContent};
use App\Services\{PublicQuoteChoices,MediaStorage,StaffQuoteData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class PublicCatalogueQuoteTest extends TestCase
{
    use RefreshDatabase;
    private function product(): Product
    {
        return Product::create(['reference'=>'TEST-STORE','published'=>true,'details'=>['name'=>'Store personnalisé','type'=>'store','available'=>true]]);
    }
    private function payload(Product $p): array
    {
        return ['contact'=>['civility'=>'mme','firstName'=>'Client','lastName'=>'Test','phone'=>'+2250700000000','address'=>'Rue du test','city'=>'Abidjan'],
            'product'=>['model'=>'product-'.$p->id,'fabric'=>'store-lourd','finish'=>'store-standard','control'=>'motorisee','mount'=>'murale','orderType'=>'installation','color'=>'beige'],
            'dimensions'=>[['label'=>'Salon','width'=>200,'height'=>220,'quantity'=>1]],'acceptTerms'=>true];
    }
    public function test_only_current_database_products_and_options_are_accepted_and_no_power_is_required(): void
    {
        $this->seed();$p=$this->product();$data=$this->payload($p);
        $this->postJson('/api/quotes/stores',$data)->assertCreated();
        $saved=Quote::firstOrFail();$this->assertSame('Store personnalisé',$saved->product['model']);$this->assertSame($p->id,$saved->product['model_id']);
        $this->assertArrayNotHasKey('powerSource',$saved->product);
        $this->postJson('/api/quotes/stores',[...$data,'product'=>[...$data['product'],'model'=>'enrouleur']])->assertUnprocessable()->assertJsonValidationErrors('product.model');
        $this->postJson('/api/quotes/stores',[...$data,'product'=>[...$data['product'],'fabric'=>'voile']])->assertUnprocessable()->assertJsonValidationErrors('product.fabric');
        $p->update(['published'=>false]);
        $this->postJson('/api/quotes/stores',$data)->assertUnprocessable()->assertJsonValidationErrors('product.model');
        $p->update(['published'=>true]);
        QuoteOption::where('code','store-standard')->update(['active'=>false]);
        $this->postJson('/api/quotes/stores',$data)->assertUnprocessable()->assertJsonValidationErrors('product.finish');
        $this->assertSame('Store personnalisé',$saved->fresh()->product['model']);
        $this->assertDatabaseCount('quotes',1);
    }
    public function test_product_images_are_reused_without_leaking_costs_and_access_is_checked(): void
    {
        $this->seed();$p=$this->product();$asset=MediaStorage::fromBytes(UploadedFile::fake()->image('store.png',100,80)->get());
        $p->media_asset_id=$asset->id;$p->save();
        $models=PublicQuoteChoices::models();$options=PublicQuoteChoices::data();
        $this->assertSame(route('quote.product-image',$p),$models[0]['imageUrl']);
        $material=collect($options)->firstWhere('id','store-lourd');$this->assertSame($models[0]['imageUrl'],$material['imageUrl']);
        $this->assertArrayNotHasKey('unit_price',$material);
        $this->get($models[0]['imageUrl'])->assertOk()->assertHeader('Content-Type','image/png');
        $this->get(route('staff.quote-product-image',$p))->assertRedirect('/admin/connexion');
        $p->update(['published'=>false]);$this->get($models[0]['imageUrl'])->assertNotFound();
        $agent=User::create(['username'=>'AgentPhoto','name'=>'Agent','email'=>'agent-photo@example.invalid','role'=>'agent','password'=>'TestPassword123!','must_change_password'=>false]);$this->actingAs($agent);
        $this->get(route('staff.quote-product-image',$p))->assertOk();
        $option=collect(StaffQuoteData::catalogue(false)['options'])->firstWhere('code','store-lourd');
        $this->assertArrayNotHasKey('unit_price',$option);$this->assertSame(route('staff.quote-product-image',$p),$option['imageUrl']);
    }
    public function test_store_without_public_finishes_can_be_requested_but_unpublished_codes_are_rejected(): void
    {
        $this->seed();$p=$this->product();$data=$this->payload($p);
        QuoteOption::where('category','store')->where('kind','finish')->update(['public_choice'=>false]);
        $this->postJson('/api/quotes/stores',$data)->assertUnprocessable()->assertJsonValidationErrors('product.finish');
        $data['product']['finish']='';
        $this->postJson('/api/quotes/stores',$data)->assertCreated();
        $this->assertNull(Quote::firstOrFail()->product['finish']);
        $this->assertDatabaseCount('quotes',1);
    }
    public function test_start_card_image_is_editable_by_admin_and_rendered_on_quote_page(): void
    {
        $this->withoutVite();$user=User::create(['username'=>'CardAdmin','name'=>'Admin','email'=>'card@example.invalid','role'=>'admin','password'=>'TestPassword123!','must_change_password'=>false]);$this->actingAs($user);
        $card=SiteContent::where('slot','quote-store')->firstOrFail();
        $this->put('/admin/contents/'.$card->id,['title'=>'Devis Store','description'=>'Choisissez votre store','position'=>0,'published'=>1,'image'=>UploadedFile::fake()->image('start.png',320,180),'image_alt'=>'Carte store'])->assertSessionHasNoErrors();
        $this->get('/devis')->assertOk()->assertSee($card->fresh()->media->url(),false)->assertSee('Carte store');
        $this->get('/admin/contents')->assertOk()->assertSee('Carte — démarrer un devis');
    }
}
