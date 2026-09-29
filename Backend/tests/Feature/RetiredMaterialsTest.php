<?php
namespace Tests\Feature;
use App\Models\{Quote,QuoteOption,User};
use App\Services\{PublicQuoteChoices,StaffQuoteData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RetiredMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_materials_are_removed_from_choices_without_changing_quotes_or_returning_on_seed(): void
    {
        $this->seed();
        $codes=['lin','coton','polyester','velours','voilage','toile','screen','aluminium','bois'];
        foreach ($codes as $code) QuoteOption::create(['code'=>$code,'kind'=>'material','category'=>'rideau','label'=>'Ancien matériau '.$code,'motorization'=>'any','active'=>true,'public_choice'=>true]);
        $old=QuoteOption::where('code','lin')->firstOrFail();
        $custom=QuoteOption::create(['code'=>'custom-kept','kind'=>'material','category'=>'rideau','label'=>'Nouveau matériau personnalisé','motorization'=>'any','active'=>true]);
        $quote=Quote::create(['reference'=>(string)Str::uuid(),'type'=>'rideau','contact'=>['lastName'=>'Archive test'],'product'=>['fabric'=>$old->label],
            'dimensions'=>[['label'=>'Salon','width'=>120,'height'=>200,'quantity'=>1,'material_id'=>$old->id,'material_name'=>$old->label]],
            'lines'=>[['description'=>'Ancien devis','quantity'=>1,'unit_price'=>12345]]]);
        $snapshot=$quote->fresh()->getAttributes();
        $migration=require database_path('migrations/2026_09_29_000009_retire_legacy_materials.php');
        $migration->retireLegacyMaterials();
        $this->seed(); $migration->retireLegacyMaterials();
        $this->assertSame(9,QuoteOption::onlyTrashed()->whereIn('code',$codes)->count());
        $this->assertSame(9,QuoteOption::withTrashed()->whereIn('code',$codes)->count());
        $this->assertSame(0,QuoteOption::whereIn('code',$codes)->count());
        $this->assertNotNull(QuoteOption::find($custom->id));
        $this->assertSame(7,QuoteOption::where('kind','material')->where('code','!=','custom-kept')->count());
        $this->assertSame($snapshot,$quote->fresh()->getAttributes());
        $this->assertNotContains($old->id,array_column(StaffQuoteData::catalogue()['options'],'id'));
        $this->assertNotContains('lin',array_column(PublicQuoteChoices::data(),'id'));
        $this->assertNotNull(QuoteOption::where('code','wave')->first());
        $this->actingAs(User::create(['username'=>'ArchiveTest','name'=>'Admin','email'=>'archive@example.invalid','password'=>'TestOnly123!','role'=>'admin','must_change_password'=>false]));
        $this->get('/admin/options-devis')->assertOk()->assertDontSee('Ancien matériau lin')->assertSee('Tissu occultant 100 %')->assertSee('Nouveau matériau personnalisé');
        $this->get('/admin/devis/'.$quote->id)->assertOk()->assertSee('Ancien matériau lin');
    }
}
