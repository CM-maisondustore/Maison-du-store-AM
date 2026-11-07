<?php
namespace Tests\Feature;
use App\Models\{CommunicationTemplate,Contact,Quote};
use App\Services\{CommunicationTemplates,DocumentAssets,DocumentLayout,DocumentPreview,DocumentViewModel,Documents,MdsTemplates};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MdsTemplatesTest extends TestCase
{
    use RefreshDatabase;
    public function test_apply_publishes_one_page_layout_and_keeps_previous_versions(): void
    {
        $this->seed();
        $quote=CommunicationTemplate::where('channel','pdf')->where('key','quote')->firstOrFail();
        $quote->update(['options'=>[...($quote->options ?? []),'logo'=>null,'letterhead_opacity'=>80]]);
        $before=$quote->fresh()->version;
        $this->artisan('mds:templates',['--force'=>true])->assertSuccessful();
        foreach (['global','quote','invoice','receipt'] as $key) {
            $p=DocumentLayout::presentation($key);
            $this->assertSame(2,$p['renderer_version']); $this->assertFalse($p['show_cover']); $this->assertSame('',$p['introduction']);
            $this->assertSame(MdsTemplates::SLOGAN,$p['footer']); $this->assertSame('#D4A11A',$p['accent']);
            $this->assertStringContainsString('CI-ABJ-03-2026-B13-09023',$p['issuer_registration']);
            $ids=array_column($p['blocks']['items'],'id');
            $this->assertContains('lines',$ids); $this->assertContains('total',$ids); $this->assertContains('header-rule',$ids);
            $this->assertSame($key==='quote' || $key==='global',in_array('commitment',$ids,true));
            $this->assertSame($key!=='receipt',in_array('payment',$ids,true));
        }
        $quote->refresh();
        $this->assertSame($before+1,$quote->version); $this->assertSame($quote->version,$quote->published_version);
        $this->assertMatchesRegularExpression('/^asset:[a-f0-9]{64}\.png$/',$quote->options['logo']);
        $this->assertSame(80,$quote->options['letterhead_opacity']);
        $this->assertStringStartsWith('data:image/png;base64,',DocumentAssets::resolve(DocumentLayout::presentation('invoice'))['logo']);
        $this->assertTrue(DB::table('document_template_revisions')->where('communication_template_id',$quote->id)->where('version',$before)->exists());
        $this->assertNotEmpty(DocumentLayout::presentation('invoice')['issuer_payment_terms']);
    }
    public function test_quote_pdf_follows_the_validated_format(): void
    {
        $this->seed(); MdsTemplates::apply();
        $data=DocumentPreview::data('quote','four',DocumentLayout::presentation('quote'));
        $html=view('documents.pdf',DocumentViewModel::prepare($data))->render();
        foreach (['DEVIS','Maison du Store AM','Cocody, Marché Saint-Jean','07 12 44 93 19','TOTAL GÉNÉRAL','Conditions de paiement','Notre engagement.','l’acompte n’est plus remboursable','Bon pour accord',MdsTemplates::SLOGAN] as $text)
            $this->assertStringContainsString(e($text),$html);
        $pdf=app(Documents::class)->render($data);
        preg_match_all('~/Type\s*/Page\b~',$pdf,$pages); $this->assertCount(1,$pages[0]);
    }
    public function test_prefilled_messages_match_channel_and_payment_conditions(): void
    {
        $this->seed(); MdsTemplates::apply();
        $contact=Contact::create(['name'=>'Awa Exemple','email'=>'awa@example.invalid','phone'=>'+2250700000000']);
        $quote=Quote::create(['reference'=>'AM-2026-1107-AWA','type'=>'store','status'=>'approved','source'=>'site','contact_id'=>$contact->id,'contact'=>['firstName'=>'Awa','lastName'=>'Exemple','email'=>$contact->email,'phone'=>$contact->phone],
            'product'=>[],'dimensions'=>[],'lines'=>[['description'=>'Pose','quantity'=>2,'unit_price'=>25000]],'tax_basis_points'=>0,'deposit_amount'=>35000,'valid_until'=>'2026-12-01']);
        $context=CommunicationTemplates::context($quote);
        $email=CommunicationTemplates::draft('quote',$context,'email');
        $this->assertSame('Votre devis AM-2026-1107-AWA – 50 000 F CFA',$email['subject']);
        $this->assertStringContainsString('en pièce jointe',$email['body']);
        $this->assertStringContainsString(MdsTemplates::SLOGAN,$email['body']);
        $this->assertStringNotContainsString("\n\n\n",$email['body']);
        $wa=CommunicationTemplates::draft('quote',$context,'whatsapp');
        $this->assertStringNotContainsString('pièce jointe',$wa['body']);
        $this->assertStringContainsString('*AM-2026-1107-AWA*',$wa['body']);
        $this->assertStringContainsString('lien ci-dessous',$wa['body']);
        foreach (['email','whatsapp'] as $channel) foreach (array_keys(CommunicationTemplates::LABELS) as $key) {
            [$subject,$body]=MdsTemplates::message($key,$channel);
            CommunicationTemplates::validateText($subject); CommunicationTemplates::validateText($body);
            $this->assertSame($body,CommunicationTemplate::where('channel',$channel)->where('key',$key)->value('body'));
            if ($channel==='whatsapp') $this->assertLessThan(3000,mb_strlen($body));
        }
    }
    public function test_two_step_schedule_reads_deposit_at_order(): void
    {
        $text=\App\Services\Quotes\QuoteFinancials::scheduleText(['schedule'=>[['key'=>'validation','label'=>'Acompte à la commande','percent'=>'70','amount'=>70000],['key'=>'pose','label'=>'Solde à la livraison et pose','percent'=>'30','amount'=>30000]]]);
        $this->assertSame('70 % à la commande (70 000 F CFA) ; 30 % à la livraison et pose (30 000 F CFA).',$text);
    }
}
