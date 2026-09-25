<?php
namespace Tests\Feature;
use App\Models\{Quote,User,Product,QuoteOption,QuotePayment,Contact};
use App\Services\Documents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class StaffQuoteFormTest extends TestCase
{
    use RefreshDatabase;
    private function staff(string $role='admin'): User { return User::create(['username'=>(string)Str::uuid(),'name'=>'Test équipe','email'=>Str::random(8).'@example.invalid','password'=>'TestPassword123!','role'=>$role,'must_change_password'=>false]); }
    private function selection(string $category='rideau'): array
    {
        $product=$category==='store' ? Product::where('reference','ST-003')->firstOrFail() : Product::where('details->type','rideau')->firstOrFail();
        return ['category'=>$category,'product_id'=>$product->id,'motorized'=>0,'material_id'=>QuoteOption::where('code',$category==='store' ? 'aluminium' : 'lin')->value('id'),'finish_id'=>QuoteOption::where('code',$category==='store' ? 'standard' : 'wave')->value('id')];
    }
    private function data(): array
    {
        return ['submission_token'=>(string)Str::uuid(),'edit_version'=>0,'contact'=>['firstName'=>'Client','lastName'=>'Test','phone'=>'+2250700000000','address'=>'Adresse de test','email'=>null],
            'defaults'=>$this->selection(),'windows'=>[[...$this->selection(),'label'=>'Salon','width'=>'120,5','height'=>220,'quantity'=>1,'inherit_defaults'=>1],[...$this->selection('store'),'label'=>'Bureau','width'=>90,'height'=>140,'quantity'=>2]],
            'quote_date'=>now()->format('Y-m-d'),'valid_until'=>now()->addDays(30)->format('Y-m-d'),'delivery_date'=>now()->addDays(7)->format('Y-m-d'),'installation_date'=>now()->addDays(9)->format('Y-m-d'),
            'payment_mode'=>'advance','deposit_amount'=>50000,'lines'=>[['description'=>'Projet complet','quantity'=>1,'unit_price'=>100000]],'tax_rate'=>0,
            'receipt_token'=>(string)Str::uuid(),'receipt_amount'=>20000,'receipt_date'=>now()->format('Y-m-d'),'receipt_method'=>'bank','receipt_confirmed'=>1,'receipt_reference'=>'VIR-TEST'];
    }
    public function test_admin_and_agent_create_window_quotes_with_database_choices_and_archives(): void
    {
        $this->seed();
        foreach (['admin','agent'] as $role) {
            auth()->logout(); session()->flush(); $user=$this->staff($role); $this->actingAs($user);
            $this->get('/admin/devis/nouveau')->assertOk()->assertSee('Choix communs');
            $data=$this->data(); $data['agent_id']=9999;
            if ($role==='admin') unset($data['agent_id']);
            $this->post('/admin/devis',$data)->assertSessionHasNoErrors()->assertRedirect();
            $quote=Quote::where('submission_token',$data['submission_token'])->firstOrFail();
            $this->assertSame('draft',$quote->status); $this->assertSame($user->id,$quote->created_by);
            $this->assertSame($role==='agent' ? $user->id : null,$quote->agent_id);
            $this->assertSame(120.5,$quote->dimensions[0]['width']);
            $this->assertSame('Wave',$quote->dimensions[0]['finish_name']); $this->assertSame('Aluminium',$quote->dimensions[1]['material_name']);
            $this->assertSame('store',$quote->dimensions[1]['category']); $this->assertNotNull($quote->contact_id);
            $this->assertSame(20000,$quote->receivedAmount());
            $this->get('/admin/devis/'.$quote->id)->assertOk()->assertSee('Wave')->assertSee('20 000');
            $this->get('/admin/devis/'.$quote->id.'/formulaire')->assertOk()->assertSee('value="120.5"',false);
        }
    }
    public function test_save_replay_does_not_duplicate_quote_contact_or_receipt(): void
    {
        $this->seed(); $this->actingAs($this->staff()); $data=$this->data();
        $this->post('/admin/devis',$data)->assertSessionHasNoErrors();
        $this->post('/admin/devis',$data)->assertRedirect();
        $this->assertDatabaseCount('quotes',1); $this->assertDatabaseCount('contacts',1); $this->assertDatabaseCount('quote_payments',1);
        $quote=Quote::firstOrFail(); $payment=[...$data,'receipt_token'=>(string)Str::uuid(),'receipt_amount'=>15000];
        $this->post('/admin/devis/'.$quote->id.'/paiements',$payment)->assertSessionHasNoErrors();
        $this->post('/admin/devis/'.$quote->id.'/paiements',$payment)->assertSessionHasNoErrors();
        $this->assertSame(35000,$quote->receivedAmount()); $this->assertDatabaseCount('quote_payments',2);
    }
    public function test_incompatible_choices_invalid_dates_and_excess_receipts_roll_back_everything(): void
    {
        $this->seed(); $this->actingAs($this->staff()); $valid=$this->data();
        $invalid=$valid; $invalid['windows'][1]['finish_id']=QuoteOption::where('code','wave')->value('id');
        $this->post('/admin/devis',$invalid)->assertSessionHasErrors('windows.1.finish_id');
        $invalid=$valid; $invalid['defaults']['finish_id']=QuoteOption::where('code','oeillets')->value('id'); $invalid['defaults']['motorized']=1;
        $this->post('/admin/devis',$invalid)->assertSessionHasErrors('defaults.finish_id');
        $this->post('/admin/devis',[...$valid,'installation_date'=>now()->addDays(2)->format('Y-m-d')])->assertSessionHasErrors('installation_date');
        $this->post('/admin/devis',[...$valid,'receipt_amount'=>100001])->assertSessionHasErrors('receipt_amount');
        $this->post('/admin/devis',[...$valid,'receipt_confirmed'=>0])->assertSessionHasErrors('receipt_amount');
        $this->assertDatabaseCount('quotes',0); $this->assertDatabaseCount('contacts',0); $this->assertDatabaseCount('quote_payments',0);
    }
    public function test_edit_preserves_individual_windows_and_rejects_stale_tabs_or_lower_totals(): void
    {
        $this->seed(); $this->actingAs($this->staff()); $data=$this->data();
        $this->post('/admin/devis',$data); $quote=Quote::firstOrFail();
        $edit=[...$data,'receipt_amount'=>0,'deposit_amount'=>0]; $edit['windows'][1]['width']=150;
        $this->put('/admin/devis/'.$quote->id.'/formulaire',$edit)->assertSessionHasNoErrors();
        $this->assertEquals(150,$quote->fresh()->dimensions[1]['width']);
        $this->assertSame(1,$quote->fresh()->edit_version); $this->assertSame(20000,$quote->receivedAmount());
        $this->put('/admin/devis/'.$quote->id.'/formulaire',$edit)->assertSessionHasErrors('edit_version');
        $edit['edit_version']=1; $edit['lines'][0]['unit_price']=19000;
        $this->put('/admin/devis/'.$quote->id.'/formulaire',$edit)->assertSessionHasErrors('lines');
        $this->assertSame(100000,$quote->fresh()->lines[0]['unit_price']);
        $this->put('/admin/devis/'.$quote->id,['lines'=>$data['lines'],'tax_rate'=>0,'valid_until'=>$data['valid_until'],'status'=>'approved'])->assertSessionHasNoErrors();
        $this->assertSame('Aluminium',$quote->fresh()->dimensions[1]['material_name']);
    }
    public function test_agents_cannot_read_or_edit_other_quotes_or_void_receipts_and_accepted_quotes_stay_locked(): void
    {
        $this->seed(); $agent=$this->staff('agent'); $this->actingAs($agent); $data=$this->data();
        $this->post('/admin/devis',$data); $quote=Quote::firstOrFail(); $payment=QuotePayment::firstOrFail();
        $this->post('/admin/devis/'.$quote->id.'/paiements/'.$payment->id.'/annuler',['void_reason'=>'Erreur de saisie'])->assertForbidden();
        $this->get('/admin/options-devis')->assertForbidden();
        $quote->update(['status'=>'accepted']);
        $this->put('/admin/devis/'.$quote->id.'/formulaire',$data)->assertConflict();
        auth()->logout(); session()->flush(); $this->actingAs($this->staff('agent'));
        $this->get('/admin/devis/'.$quote->id.'/formulaire')->assertForbidden();
        $this->post('/admin/devis/'.$quote->id.'/paiements',$data)->assertForbidden();
        $this->put('/admin/devis/'.$quote->id.'/formulaire',$data)->assertForbidden();
    }
    public function test_admin_void_keeps_history_and_restores_remaining_balance(): void
    {
        $this->seed(); $this->actingAs($this->staff()); $this->post('/admin/devis',$this->data());
        $quote=Quote::firstOrFail(); $payment=QuotePayment::firstOrFail();
        $this->post('/admin/devis/'.$quote->id.'/paiements/'.$payment->id.'/annuler',['void_reason'=>'Doublon de saisie'])->assertSessionHasNoErrors();
        $this->assertSame(0,$quote->receivedAmount()); $this->assertNotNull($payment->fresh()->voided_at); $this->assertDatabaseCount('quote_payments',1);
    }
    public function test_pdf_and_saved_choices_survive_option_renaming_and_reseed(): void
    {
        $this->seed(); $this->actingAs($this->staff()); $this->post('/admin/devis',$this->data()); $quote=Quote::firstOrFail();
        QuoteOption::where('code','wave')->update(['label'=>'Wave modifié','active'=>false]); $this->seed();
        $snapshot=app(Documents::class)->quoteData($quote->fresh());
        $this->assertSame('Wave',$snapshot['windows'][0]['finish_name']); $this->assertSame(20000,$snapshot['payment_plan']['received']);
        $this->assertFalse(QuoteOption::where('code','wave')->first()->active);
        $this->get('/admin/devis/'.$quote->id.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->get('/admin/options-devis')->assertOk()->assertSee('Wave modifié');
    }
    public function test_admin_configures_compatible_options_and_other_roles_cannot_change_them(): void
    {
        $this->seed(); $this->actingAs($this->staff()); $selection=$this->selection();
        $data=['label'=>'Finition test','kind'=>'finish','category'=>'rideau','motorization'=>'yes','active'=>1,'product_ids'=>[$selection['product_id']]];
        $this->post('/admin/options-devis',$data)->assertSessionHasNoErrors();
        $option=QuoteOption::where('label','Finition test')->firstOrFail();
        $this->assertTrue($option->compatible('rideau',$selection['product_id'],true));
        $this->assertFalse($option->compatible('rideau',$selection['product_id'],false));
        $this->put('/admin/options-devis/'.$option->id,[...$data,'product_ids'=>[$this->selection('store')['product_id']]])->assertSessionHasErrors('product_ids');
        $this->assertSame([$selection['product_id']],$option->fresh()->product_ids);
        auth()->logout(); session()->flush(); $this->actingAs($this->staff('agent'));
        $this->post('/admin/options-devis',$data)->assertForbidden();
        $this->put('/admin/options-devis/'.$option->id,$data)->assertForbidden();
    }
}
