<?php
namespace Tests\Feature;
use App\Models\{CommunicationTemplate,Quote,QuotePayment,User,DocumentIssue,DocumentArchive,CommercialMessage};
use App\Services\{DocumentLayout,DocumentPreview,DocumentViewModel,Documents,DocumentAssets,DocumentIssues};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentLayoutsTest extends TestCase
{
    use RefreshDatabase;
    private function admin(string $role='admin'): User
    {
        return User::create(['username'=>'Layout'.$role,'name'=>'Test','email'=>$role.'@example.invalid','role'=>$role,'password'=>'Testing123!','must_change_password'=>false]);
    }
    private function settings(int $version=1): array
    {
        return [...DocumentLayout::defaults(),'version'=>$version,'name'=>'Modèle test','body'=>'Projet de démonstration','issuer_name'=>'Maison du Store AM','issuer_address'=>'Abidjan','issuer_registration'=>'TEST','issuer_tax_note'=>'Mention fiscale','issuer_payment_terms'=>'Conditions complémentaires','scenario'=>'single','preview_type'=>'quote','action'=>'draft'];
    }
    private function quote(): Quote
    {
        $data=DocumentPreview::data('quote','four',DocumentLayout::defaults());
        return Quote::create(['reference'=>'LAYOUT-TEST','type'=>'rideau','status'=>'approved','contact'=>$data['customer'],'product'=>[],'dimensions'=>[],
            'configuration'=>['calculation'=>['financial'=>$data['financial']]],'lines'=>[],'tax_basis_points'=>0,'deposit_amount'=>190313,'payment_mode'=>'advance','valid_until'=>'2026-12-01']);
    }
    public function test_draft_preview_publish_and_version_conflicts(): void
    {
        $this->seed(); $this->actingAs($this->admin()); $t=CommunicationTemplate::where('key','quote')->where('channel','pdf')->firstOrFail();
        $before=$t->getAttributes(); $data=$this->settings();
        $this->get('/admin/mise-en-page/quote')->assertOk()->assertSee('Mise en page des documents')->assertSee('Enregistrer le brouillon');
        $this->post('/admin/mise-en-page/quote/apercu',$data)->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertSame($before,$t->fresh()->getAttributes());
        $this->assertDatabaseCount('document_archives',0); $this->assertDatabaseCount('document_issues',0); $this->assertDatabaseCount('quotes',0);
        $this->post('/admin/mise-en-page/quote',$data)->assertSessionHasNoErrors();
        $this->assertSame(1,DocumentLayout::presentation('quote')['template_version']);
        $this->assertSame(1,DocumentLayout::presentation('quote')['renderer_version'] ?? 1);
        $this->assertSame(2,$t->fresh()->layout_draft['options']['renderer_version']);
        $this->post('/admin/mise-en-page/quote',[...$data,'action'=>'publish'])->assertSessionHasErrors('version');
        $this->post('/admin/mise-en-page/quote',[...$data,'version'=>2,'action'=>'publish'])->assertSessionHasNoErrors();
        $this->assertSame(2,DocumentLayout::presentation('quote')['renderer_version']); $this->assertNull($t->fresh()->layout_draft);
        $revision=DB::table('document_template_revisions')->where('communication_template_id',$t->id)->oldest('id')->first();
        $this->post('/admin/mise-en-page/quote/versions/'.$revision->id,['version'=>3])->assertSessionHasNoErrors();
        $this->assertNotNull($t->fresh()->layout_draft); $this->assertSame(2,DocumentLayout::presentation('quote')['renderer_version']);
    }
    public function test_global_publication_is_atomic_and_does_not_override_another_edit(): void
    {
        $this->seed(); $this->actingAs($this->admin());
        $versions=CommunicationTemplate::where('channel','pdf')->pluck('version','id')->all();
        $this->post('/admin/mise-en-page/global',[...$this->settings(),'action'=>'publish','apply_all'=>1,'target_versions'=>[]])->assertSessionHasErrors('version');
        $this->assertSame(1,CommunicationTemplate::where('key','global')->first()->version);
        $this->post('/admin/mise-en-page/global',[...$this->settings(),'action'=>'publish','apply_all'=>1,'target_versions'=>$versions])->assertSessionHasNoErrors();
        foreach (['quote','invoice','receipt'] as $type) $this->assertSame('#C9A037',DocumentLayout::presentation($type)['accent']);
    }
    public function test_issued_quotes_and_receipts_are_byte_identical_after_template_and_payment_changes(): void
    {
        $this->seed(); $user=$this->admin(); $this->actingAs($user);
        CommunicationTemplate::where('channel','pdf')->where('key','quote')->first()->update(['options'=>[...DocumentLayout::defaults(),'show_cover'=>false]]);
        $quote=$this->quote(); $documents=app(Documents::class); $bytes=$documents->quote($quote);
        $this->assertStringStartsWith('%PDF',$bytes); $this->assertDatabaseCount('document_issues',1);
        CommunicationTemplate::where('channel','pdf')->where('key','quote')->first()->update(['options'=>[...DocumentLayout::defaults(),'accent'=>'#FF0000']]);
        $payment=QuotePayment::create(['quote_id'=>$quote->id,'recorded_by'=>$user->id,'token'=>(string)\Illuminate\Support\Str::uuid(),'amount'=>50000,'received_on'=>'2026-09-30','method'=>'cash']);
        $this->assertSame($bytes,$documents->quote($quote->fresh()));
        $url='/admin/devis/'.$quote->id.'/paiements/'.$payment->id.'/recu';
        $receipt=$this->get($url)->assertOk()->getContent();
        CommunicationTemplate::where('key','receipt')->first()->update(['options'=>['accent'=>'#000000']]);
        $payment->update(['voided_at'=>now(),'void_reason'=>'Erreur de saisie']);
        $this->get($url)->assertOk()->assertContent($receipt);
        $this->get('/admin/documents/'.DocumentIssue::where('quote_payment_id',$payment->id)->first()->id.'/archive')->assertContent($receipt);
        $this->assertSame($bytes,$documents->quote($quote->fresh()));
        $new=$payment->replicate(); $new->token=(string)\Illuminate\Support\Str::uuid(); $new->save();
        $this->get('/admin/devis/'.$quote->id.'/paiements/'.$new->id.'/recu')->assertStatus(409);
    }
    public function test_existing_sent_archive_is_reused_and_metadata_never_reprices(): void
    {
        $this->seed(); $this->actingAs($this->admin()); $quote=$this->quote(); $documents=app(Documents::class); $snapshot=$documents->quoteData($quote);
        $message=CommercialMessage::create(['sender'=>'contact@maisondustoream.com','recipient'=>'example@example.invalid','subject'=>'Ancien devis','body'=>'Test','quote_id'=>$quote->id,'status'=>'sent','document_snapshot'=>$snapshot]);
        $archive=DocumentArchive::capture('%PDF-existing-original',['commercial_message_id'=>$message->id]);
        $this->assertSame($archive->contents(),$documents->quote($quote));
        $before=$quote->configuration;
        $this->post('/admin/devis/'.$quote->id.'/presentation',['edit_version'=>$quote->fresh()->edit_version,'object'=>'Projet salon','rooms'=>['Salon','Salon','Chambre','Chambre']])->assertSessionHasNoErrors();
        $this->assertSame($before,$quote->fresh()->configuration);
        $this->assertSame('Projet salon',$quote->fresh()->document_metadata['object']);
        $this->assertSame('%PDF-existing-original',$archive->fresh()->contents());
    }
    public function test_rendering_reference_values_grouping_and_multi_page_pdf(): void
    {
        $styles=[...DocumentLayout::defaults(),'show_cover'=>false];
        $fixture=DocumentPreview::data('quote','four',$styles); $view=DocumentViewModel::prepare($fixture);
        $html=view('documents.pdf',$view)->render();
        foreach (['241 875 F CFA','30 000 F CFA','271 875 F CFA','190 313 F CFA','81 562 F CFA','L 4 m × H 2 m'] as $value) $this->assertStringContainsString($value,$html);
        foreach (['single','eight','twenty','thirty','store','discount','negotiated','individual'] as $scenario) {
            $data=DocumentPreview::data('quote',$scenario,$styles); $view=DocumentViewModel::prepare($data); $html=view('documents.pdf',$view)->render();
            $this->assertStringContainsString(DocumentViewModel::money($data['financial']['total']),$html);
            $this->assertStringNotContainsString('Tissu occultant 80 %',$html);
            $this->assertSame($data['financial'],$view['totals']);
        }
        $pdf=app(Documents::class)->render(DocumentPreview::data('quote','thirty',$styles));
        preg_match_all('~/Type\s*/Page\b~',$pdf,$pages); $this->assertGreaterThan(2,count($pages[0])); $this->assertLessThan(31,count($pages[0]));
        $this->assertGreaterThan(10000,strlen($pdf));
    }
    public function test_assets_permissions_and_invalid_input(): void
    {
        $this->seed(); $this->actingAs($this->admin('agent'));
        $this->get('/admin/mise-en-page')->assertForbidden(); $this->post('/admin/mise-en-page/quote',$this->settings())->assertForbidden();
        $this->flushSession(); $this->actingAs($this->admin());
        $this->post('/admin/mise-en-page/quote',[...$this->settings(),'accent'=>'red; display:none','font_family'=>'https://example.invalid/font'])->assertSessionHasErrors(['accent','font_family']);
        $this->post('/admin/mise-en-page/quote',[...$this->settings(),'logo'=>UploadedFile::fake()->createWithContent('x.svg','<svg/>')])->assertSessionHasErrors('logo');
        $image=UploadedFile::fake()->image('logo.png',40,40);
        $this->post('/admin/mise-en-page/quote',[...$this->settings(),'logo'=>$image])->assertSessionHasNoErrors();
        $t=CommunicationTemplate::where('channel','pdf')->where('key','quote')->first();
        $this->assertStringStartsWith('asset:',$t->layout_draft['options']['logo']);
        $this->assertStringStartsWith('data:image/png;base64,',DocumentAssets::resolve($t->layout_draft['options'])['logo']);
        $this->assertDatabaseCount('commercial_messages',0);
    }
    public function test_old_validated_quotes_keep_the_baseline_but_unissued_drafts_use_the_published_model(): void
    {
        $this->seed(); $quote=$this->quote();
        $draft=$quote->replicate(); $draft->reference='DRAFT-BEFORE-LAYOUT'; $draft->status='draft'; $draft->save();
        $this->travel(1)->minutes();
        $template=CommunicationTemplate::where('channel','pdf')->where('key','quote')->first();
        DocumentLayout::revision($template);
        $template->update(['options'=>DocumentLayout::defaults(),'version'=>2,'published_version'=>2]);
        $documents=app(Documents::class);
        $this->assertSame(1,$documents->quoteData($quote)['presentation']['renderer_version'] ?? 1);
        $this->assertSame(2,$documents->quoteData($draft)['presentation']['renderer_version']);
        $this->assertDatabaseCount('document_archives',0);
    }
}
