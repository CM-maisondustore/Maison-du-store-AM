<?php
namespace Tests\Feature;

use App\Models\{Quote,QuotePayment,User};
use App\Services\{DocumentIssues,Documents};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentReferencesTest extends TestCase
{
    use RefreshDatabase;

    private function quote(array $extra=[]): Quote
    {
        return Quote::create([...['type'=>'store','status'=>'draft','contact'=>['firstName'=>'Client','lastName'=>'Test'],'product'=>[],'dimensions'=>[],
            'lines'=>[['description'=>'Store','quantity'=>1,'unit_price'=>100000]],'tax_basis_points'=>0,'deposit_amount'=>70000,'payment_mode'=>'advance'],...$extra]);
    }

    public function test_shared_sequence_starts_at_112_and_does_not_reset_or_change_existing_references(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-02 10:00:00','Africa/Abidjan'));
        $store=$this->quote(); $this->assertSame('AM-2026-10-02-ST-112',$store->reference);
        $curtain=$this->quote(['type'=>'rideau','quote_date'=>'2026-10-01']);
        $this->assertSame('AM-2026-10-01-RV-113',$curtain->reference);
        $this->travel(1)->days();
        $voile=$this->quote(['type'=>'voilage']); $this->assertSame('AM-2026-10-03-RV-114',$voile->reference);
        $store->update(['quote_date'=>'2026-10-04','type'=>'rideau']);
        $this->assertSame('AM-2026-10-02-ST-112',$store->fresh()->reference);
        $legacy=$this->quote(['reference'=>'OLD-REFERENCE']); $legacy->update(['notes'=>'Updated']);
        $this->assertSame('OLD-REFERENCE',$legacy->fresh()->reference);
        $this->assertSame(115,(int)DB::table('document_sequences')->where('name','quotes')->value('next_value'));
        DB::table('document_sequences')->where('name','quotes')->update(['next_value'=>1000]);
        $this->assertSame('AM-2026-10-03-ST-1000',$this->quote()->reference);
    }

    public function test_failed_transaction_does_not_consume_a_number(): void
    {
        try {
            DB::transaction(function () { $this->quote(); throw new \RuntimeException('abort'); });
        } catch (\RuntimeException $error) { $this->assertSame('abort',$error->getMessage()); }
        $this->assertDatabaseCount('quotes',0);
        $this->assertSame(112,(int)DB::table('document_sequences')->where('name','quotes')->value('next_value'));
        $this->assertStringEndsWith('-112',$this->quote()->reference);
    }

    public function test_receipts_keep_quote_reference_and_stable_suffix_after_voiding_or_reprinting(): void
    {
        $this->seed();
        $user=User::create(['username'=>'ReferenceAdmin','name'=>'Test','email'=>'reference@example.invalid','password'=>'TestPassword123!','role'=>'admin','must_change_password'=>false]);
        $this->actingAs($user); $quote=$this->quote(); $documents=app(Documents::class);
        $payment=QuotePayment::create(['quote_id'=>$quote->id,'recorded_by'=>$user->id,'token'=>(string)Str::uuid(),'amount'=>20000,'received_on'=>today(),'method'=>'cash']);
        $first=DocumentIssues::receipt($quote,$payment,$documents); $bytes=$first->archive->contents();
        $this->assertSame($quote->reference.'-REC-01',$first->snapshot['number']);
        $payment->update(['voided_at'=>now(),'void_reason'=>'Correction']);
        $next=$payment->replicate(); $next->token=(string)Str::uuid(); $next->voided_at=null; $next->void_reason=null; $next->save();
        $second=DocumentIssues::receipt($quote,$next,$documents);
        $this->assertSame($quote->reference.'-REC-02',$second->snapshot['number']);
        $this->assertSame($bytes,DocumentIssues::receipt($quote,$payment,$documents)->archive->contents());
        $this->assertSame($second->id,DocumentIssues::receipt($quote,$next,$documents)->id);
        $this->assertSame(113,(int)DB::table('document_sequences')->where('name','quotes')->value('next_value'));
    }
}
