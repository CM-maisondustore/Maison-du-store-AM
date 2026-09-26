<?php
namespace Tests\Feature;
use App\Models\{MediaAsset, Product, SiteContent, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class SiteContentTest extends TestCase
{
    use RefreshDatabase;
    private function staff(string $role = 'admin'): User
    {
        return User::create(['username'=>'Content'.$role,'name'=>'Content','email'=>$role.'@example.invalid','password'=>'TestingPass123!','role'=>$role,'must_change_password'=>false]);
    }
    private function png(): string { return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWZkAAAAASUVORK5CYII='); }
    private function upload(): UploadedFile { return UploadedFile::fake()->createWithContent('photo.png',$this->png()); }
    private function data(): array { return ['title'=>'Réalisation test','description'=>'Description du projet','position'=>7,'published'=>1,'image_alt'=>'Rideaux au salon']; }

    public function test_upload_is_saved_in_database_and_displayed_with_editable_portfolio_text(): void
    {
        $this->withoutVite();
        $this->actingAs($this->staff())->post('/admin/contents',[...$this->data(),'image'=>$this->upload()])->assertSessionHasNoErrors()->assertRedirect();
        $content=SiteContent::firstOrFail(); $asset=MediaAsset::findOrFail($content->media_asset_id);
        $this->assertSame($this->png(),base64_decode($asset->payload));
        $this->assertArrayNotHasKey('payload',$asset->toArray());
        $this->get('/')->assertOk()->assertSee('Description du projet')->assertSee($asset->url());
        $this->get($asset->url())->assertOk()->assertHeader('Content-Type','image/png')->assertHeader('X-Content-Type-Options','nosniff')->assertContent($this->png());
        $this->put('/admin/contents/'.$content->id,[...$this->data(),'title'=>'<script>alert(1)</script>','description'=>'Texte modifié'])->assertSessionHasNoErrors();
        $this->assertSame($asset->id,$content->fresh()->media_asset_id);
        $this->get('/')->assertSee('Texte modifié')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
        $this->get('/admin/contents/'.$content->id.'/edit')->assertOk()->assertSee('Texte modifié');
    }

    public function test_replacement_removal_and_hiding_also_protect_image_urls(): void
    {
        $this->withoutVite();
        $this->actingAs($this->staff())->post('/admin/contents',[...$this->data(),'image'=>$this->upload()]);
        $content=SiteContent::firstOrFail(); $old=MediaAsset::findOrFail($content->media_asset_id);
        $this->put('/admin/contents/'.$content->id,[...$this->data(),'image'=>$this->upload()])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('media_assets',['id'=>$old->id]); $this->get($old->url())->assertNotFound();
        $asset=MediaAsset::findOrFail($content->fresh()->media_asset_id);
        $this->put('/admin/contents/'.$content->id,[...$this->data(),'published'=>0])->assertSessionHasNoErrors();
        $this->get('/')->assertDontSee('Réalisation test');
        $this->get($asset->url())->assertNotFound();
        $this->get('/admin/media/'.$asset->id)->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->put('/admin/contents/'.$content->id,[...$this->data(),'remove_image'=>1])->assertSessionHasNoErrors();
        $this->assertNull($content->fresh()->media_asset_id); $this->assertDatabaseCount('media_assets',0);
    }

    public function test_bad_uploads_do_not_save_text_or_media_and_agents_cannot_edit(): void
    {
        $this->actingAs($this->staff());
        foreach ([UploadedFile::fake()->createWithContent('fake.png','<?php echo 1;'),UploadedFile::fake()->createWithContent('vector.svg','<svg xmlns="http://www.w3.org/2000/svg"></svg>'),$this->upload()->size(2049)] as $file) {
            $this->post('/admin/contents',[...$this->data(),'image'=>$file])->assertSessionHasErrors('image');
        }
        $this->assertDatabaseCount('site_contents',0); $this->assertDatabaseCount('media_assets',0);
        $this->post('/admin/contents',[...$this->data(),'image'=>$this->upload()])->assertSessionHasNoErrors();
        $content=SiteContent::firstOrFail();
        $this->put('/admin/contents/'.$content->id,[...$this->data(),'title'=>'Do not save','image'=>UploadedFile::fake()->createWithContent('bad.jpg','invalid')])->assertSessionHasErrors('image');
        $this->assertSame('Réalisation test',$content->fresh()->title);
        auth()->logout(); session()->flush();
        $this->get('/admin/contents')->assertRedirect('/admin/connexion');
        $this->get('/admin/media/'.$content->media_asset_id)->assertRedirect('/admin/connexion');
        $this->actingAs($this->staff('agent'));
        foreach (['/admin/contents','/admin/contents/create','/admin/contents/'.$content->id.'/edit','/admin/media/'.$content->media_asset_id] as $url) $this->get($url)->assertForbidden();
        $this->post('/admin/contents',$this->data())->assertForbidden();
        $this->put('/admin/contents/'.$content->id,$this->data())->assertForbidden();
    }

    public function test_product_image_persists_without_changing_costs_and_disappears_when_hidden(): void
    {
        $this->actingAs($this->staff());
        $product=Product::create(['reference'=>'PHOTO-1','material_cost'=>9000,'published'=>true,'details'=>['name'=>'Photo produit','type'=>'store']]);
        $data=['reference'=>'PHOTO-1','name'=>'Photo produit','type'=>'store','category'=>'Store','description'=>'Texte','quoteModel'=>'store','published'=>1,'available'=>1,'featured'=>0];
        $this->put('/admin/products/'.$product->id,[...$data,'image'=>$this->upload(),'image_alt'=>'Store photographié'])->assertSessionHasNoErrors();
        $product->refresh(); $asset=MediaAsset::findOrFail($product->media_asset_id);
        $this->assertSame(9000,$product->material_cost);
        $this->getJson('/api/products')->assertJsonPath('data.0.imageUrl',$asset->url())->assertJsonPath('data.0.imageAlt','Store photographié')->assertJsonMissingPath('data.0.payload');
        $this->put('/admin/products/'.$product->id,$data)->assertSessionHasNoErrors();
        $this->assertSame($asset->id,$product->fresh()->media_asset_id);
        $this->get($asset->url())->assertOk();
        $this->patch('/admin/catalogue/visibilite', ['visible' => 0])->assertSessionHasNoErrors();
        $this->get($asset->url())->assertNotFound();
        $this->get('/admin/media/'.$asset->id)->assertOk();
        $this->patch('/admin/catalogue/visibilite', ['visible' => 1])->assertSessionHasNoErrors();
        $this->get($asset->url())->assertOk();
        $this->patch('/admin/products/'.$product->id.'/visibility',['published'=>0,'available'=>1])->assertSessionHasNoErrors();
        $this->get($asset->url())->assertNotFound();
    }

    public function test_seed_preserves_edited_hero_and_portfolio_order_and_visibility(): void
    {
        $this->withoutVite(); $this->seed();
        $hero=SiteContent::where('slot','hero')->firstOrFail();
        $hero->update(['title'=>'Notre titre personnalisé','description'=>'Notre description']);
        SiteContent::where('slot','portfolio-initial-0')->update(['published'=>false]);
        SiteContent::where('slot','portfolio-initial-1')->update(['title'=>'Première réalisation','position'=>0]);
        SiteContent::where('slot','portfolio-initial-2')->update(['title'=>'Dernière réalisation','position'=>999]);
        $this->seed();
        $this->get('/')->assertSee('Notre titre personnalisé')->assertSee('Notre description')->assertDontSee('Appartement Haussmannien')->assertSeeInOrder(['Première réalisation','Dernière réalisation']);
        $this->assertDatabaseCount('site_contents',6); $this->assertDatabaseCount('media_assets',1);
        $this->get($hero->media->url())->assertOk()->assertHeader('Content-Type','image/webp');
    }
}
