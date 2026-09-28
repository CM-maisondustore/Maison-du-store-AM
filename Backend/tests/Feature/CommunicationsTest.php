<?php
namespace Tests\Feature;
use App\Models\{User,Contact,Quote,Appointment,Invoice,CommercialMessage,CommunicationTemplate,CommunicationRule,CommunicationCheck,DocumentArchive};
use App\Services\{CommunicationAutomation,CommunicationTemplates,MailConnection,Documents};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class CommunicationsTest extends TestCase
{
    use RefreshDatabase;
    private function admin(string $role='admin'): User
    {
        return User::create(['username'=>'Comms'.$role,'name'=>'Communications','email'=>$role.'@example.invalid','password'=>'TestingPass123!','role'=>$role,'must_change_password'=>false]);
    }
    private function quote(string $status='approved'): Quote
    {
        $contact=Contact::create(['name'=>'Awa Exemple','email'=>'awa@example.invalid','phone'=>'+2250700000000']);
        return Quote::create(['reference'=>'DEV-TEST','type'=>'store','status'=>$status,'source'=>'site','contact_id'=>$contact->id,
            'contact'=>['firstName'=>'Awa','lastName'=>'Exemple','email'=>$contact->email,'phone'=>$contact->phone,'address'=>'Abidjan'],
            'product'=>[],'dimensions'=>[],'lines'=>[['description'=>'Pose','quantity'=>2,'unit_price'=>25000]],'tax_basis_points'=>0,'valid_until'=>'2026-12-01']);
    }
    private function smtp(): void
    {
        config(['workspace.mail_enabled'=>true,'mail.default'=>'smtp','mail.mailers.smtp.password'=>'test']);
        CommunicationCheck::create(['key'=>'smtp','successful'=>true,'fingerprint'=>app(MailConnection::class)->fingerprint(),'checked_at'=>now()]);
    }
    public function test_template_edit_preview_variables_and_concurrent_update(): void
    {
        $this->seed(); $this->actingAs($this->admin());
        $t=CommunicationTemplate::where('channel','email')->where('key','quote')->firstOrFail();
        $data=['version'=>$t->version,'subject'=>'Devis {{reference}}','body'=>'Bonjour {{prenom}}, total {{montant}}. <script>test</script>'];
        $this->get('/admin/communications')->assertOk()->assertSee('Documents &amp; automatisations',false);
        $this->get('/admin/communications/modeles/'.$t->id)->assertOk()->assertSee('Objet / titre');
        $this->post('/admin/communications/modeles/'.$t->id.'/apercu',$data)->assertOk()->assertSee('Bonjour Awa')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>test</script>',false);
        $this->assertSame(1,$t->fresh()->version);
        $this->post('/admin/communications/modeles/'.$t->id,$data)->assertSessionHasNoErrors();
        $this->post('/admin/communications/modeles/'.$t->id,$data)->assertSessionHasErrors('version');
        $this->postJson('/admin/communications/modeles/'.$t->id,[...$data,'version'=>2,'body'=>'{{secret}}'])->assertUnprocessable();
        $quote=$this->quote();
        $this->get('/admin/messages/nouveau?quote_id='.$quote->id)->assertOk()->assertSee('Bonjour Awa, total 50 000.');
        $this->seed(); $this->assertSame(2,$t->fresh()->version);
    }
    public function test_pdf_preview_saves_no_data_and_archived_bytes_never_change(): void
    {
        $this->seed(); $this->actingAs($this->admin()); $this->smtp();
        $t=CommunicationTemplate::where('channel','pdf')->where('key','quote')->firstOrFail();
        $this->get('/admin/communications/modeles/'.$t->id)->assertOk()->assertSee('Logo PNG ou JPEG');
        $data=['version'=>1,'body'=>'Texte personnalisé','accent'=>'#aabbcc','footer'=>'Merci','signature'=>'Maison du Store AM','issuer_name'=>'Entreprise test','issuer_address'=>'Abidjan','issuer_registration'=>'REF TEST','issuer_tax_note'=>'Mention test','issuer_payment_terms'=>'Acompte'];
        $this->post('/admin/communications/modeles/'.$t->id.'/apercu',$data)->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertSame(1,$t->fresh()->version); $this->assertDatabaseCount('document_archives',0);
        $this->post('/admin/communications/modeles/'.$t->id,$data)->assertSessionHasNoErrors();
        $quote=$this->quote();
        $message=CommercialMessage::create(['recipient'=>$quote->contact['email'],'sender'=>'contact@maisondustoream.com','subject'=>'Devis','body'=>'Bonjour','quote_id'=>$quote->id]);
        $transport=\Mockery::mock(); $transport->shouldReceive('raw')->once()->andReturn(null); Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($transport);
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertSessionHasNoErrors();
        $archive=$message->fresh()->archive; $this->assertNotNull($archive); $bytes=$archive->contents();
        $this->assertStringStartsWith('%PDF',$bytes);
        $t->update(['body'=>'Autre texte','options'=>['accent'=>'#000000']]); $quote->update(['lines'=>[]]);
        $this->get('/admin/messages/'.$message->id.'/document')->assertContent($bytes);
        $this->assertSame(hash('sha256',$bytes),$archive->sha256);
    }
    public function test_rules_default_to_off_and_draft_processing_is_idempotent(): void
    {
        $this->seed(); $this->actingAs($this->admin()); $quote=$this->quote('pending'); $this->travel(1)->minutes();
        $rule=CommunicationRule::where('key','quote_received')->firstOrFail();
        $this->assertSame(0,app(CommunicationAutomation::class)->process()['drafts']);
        $this->put('/admin/communications/regles/'.$rule->id,['mode'=>'draft','delay_hours'=>24])->assertSessionHasNoErrors();
        $this->assertSame(0,app(CommunicationAutomation::class)->process()['drafts']);
        $new=$quote->replicate(); $new->reference='NEW'; $new->save();
        $this->assertSame(1,app(CommunicationAutomation::class)->process()['drafts']);
        $this->assertSame(0,app(CommunicationAutomation::class)->process()['drafts']);
        $message=CommercialMessage::firstOrFail(); $this->assertSame('draft',$message->status); $this->assertFalse($message->auto_send);
        $this->assertStringContainsString('NEW',$message->body);
        $this->assertDatabaseCount('document_archives',0);
    }
    public function test_automatic_activation_requires_connection_and_ack_has_no_pdf(): void
    {
        $this->seed(); $this->actingAs($this->admin());
        $rule=CommunicationRule::where('key','quote_received')->firstOrFail();
        $this->put('/admin/communications/regles/'.$rule->id,['mode'=>'automatic','delay_hours'=>24])->assertSessionHasErrors('mode');
        $this->smtp();
        $this->put('/admin/communications/regles/'.$rule->id,['mode'=>'automatic','delay_hours'=>24])->assertSessionHasNoErrors();
        $this->quote('pending');
        $transport=\Mockery::mock(); $transport->shouldReceive('raw')->once()->andReturn(null); Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($transport);
        $this->assertSame(1,app(CommunicationAutomation::class)->process()['sent']);
        $this->assertSame(0,app(CommunicationAutomation::class)->process()['sent']);
        $this->assertDatabaseCount('document_archives',0);
        $this->assertNull(CommercialMessage::firstOrFail()->document_snapshot);
    }
    public function test_reminders_exclude_cancelled_past_and_stale_appointments(): void
    {
        $this->seed(); $this->actingAs($this->admin()); $contact=$this->quote()->customer;
        CommunicationRule::where('key','reminder')->update(['mode'=>'draft','activated_at'=>now(),'delay_hours'=>24]);
        $base=['contact_id'=>$contact->id,'details'=>['firstName'=>'Awa','email'=>$contact->email],'time'=>now()->addHours(2)->format('H:i')];
        $appointment=Appointment::create([...$base,'reference'=>'FUTURE','date'=>now()->addHours(2)->format('Y-m-d'),'status'=>'confirmed']);
        Appointment::create([...$base,'reference'=>'PAST','time'=>'00:00','date'=>now()->subDay()->toDateString(),'status'=>'confirmed']);
        Appointment::create([...$base,'reference'=>'CANCELLED','date'=>now()->addDay()->toDateString(),'status'=>'cancelled']);
        $this->assertSame(1,app(CommunicationAutomation::class)->process()['drafts']);
        $this->assertSame(0,app(CommunicationAutomation::class)->process()['drafts']);
        $message=CommercialMessage::firstOrFail(); $appointment->update(['status'=>'cancelled']); $this->smtp();
        Mail::shouldReceive('mailer')->never();
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertSessionHasErrors('mail');
        $this->assertSame('draft',$message->fresh()->status);
    }
    public function test_whatsapp_is_manual_and_cannot_be_sent_by_email(): void
    {
        $this->seed(); $this->actingAs($this->admin()); $this->smtp(); $quote=$this->quote();
        $this->get('/admin/messages/nouveau?channel=whatsapp&quote_id='.$quote->id)->assertOk()->assertSee('+2250712449319');
        $this->post('/admin/messages',['channel'=>'whatsapp','recipient'=>'+225 07 00 00 00 00','subject'=>'Votre devis','body'=>'Bonjour Awa','kind'=>'devis','quote_id'=>$quote->id])->assertSessionHasNoErrors();
        $message=CommercialMessage::firstOrFail();
        $this->get('/admin/messages/'.$message->id.'/whatsapp')->assertRedirect('https://wa.me/2250700000000?text=Bonjour%20Awa');
        $this->assertSame('draft',$message->fresh()->status);
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertUnprocessable();
        $this->post('/admin/messages/'.$message->id.'/partage')->assertSessionHasNoErrors();
        $this->assertSame('shared',$message->fresh()->status);
    }
    public function test_agents_cannot_manage_templates_or_rules(): void
    {
        $this->seed(); $template=CommunicationTemplate::firstOrFail();
        $this->get('/admin/communications')->assertRedirect('/admin/connexion');
        $this->actingAs($this->admin('agent'))->get('/admin/communications')->assertForbidden();
        $this->post('/admin/communications/modeles/'.$template->id,['version'=>1])->assertForbidden();
        $this->post('/admin/communications/connexion')->assertForbidden();
    }
    public function test_issued_invoice_keeps_exact_pdf_when_model_changes(): void
    {
        $this->seed(); $this->actingAs($this->admin()); $quote=$this->quote('accepted');
        $t=CommunicationTemplate::where('channel','pdf')->where('key','invoice')->firstOrFail();
        $t->update(['options'=>['issuer_name'=>'Maison du Store AM','issuer_address'=>'Abidjan','issuer_registration'=>'TEST','issuer_tax_note'=>'Mention test','issuer_payment_terms'=>'Paiement prévu']]);
        $this->post('/admin/devis/'.$quote->id.'/facture',['due_date'=>'2026-12-01'])->assertSessionHasNoErrors();
        $invoice=Invoice::firstOrFail();
        $this->post('/admin/factures/'.$invoice->id.'/emettre')->assertSessionHasNoErrors();
        $bytes=DocumentArchive::where('invoice_id',$invoice->id)->firstOrFail()->contents();
        $t->update(['body'=>'Modèle suivant']);
        $this->get('/admin/factures/'.$invoice->id.'/pdf')->assertContent($bytes);
        $this->post('/admin/factures/'.$invoice->id.'/emettre')->assertConflict();
        $this->assertDatabaseCount('document_archives',1);
    }
    public function test_mail_check_authenticates_without_sending_and_detects_changed_configuration(): void
    {
        $this->actingAs($this->admin()); config(['workspace.mail_enabled'=>true,'mail.default'=>'smtp','mail.mailers.smtp.password'=>'test']);
        $transport=\Mockery::mock(); $transport->shouldReceive('start')->once(); $transport->shouldReceive('stop')->once();
        $mailer=\Mockery::mock(); $mailer->shouldReceive('getSymfonyTransport')->once()->andReturn($transport);
        Mail::shouldReceive('purge')->with('smtp')->once(); Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($mailer);
        $this->post('/admin/communications/connexion')->assertSessionHasNoErrors();
        $this->assertTrue(app(MailConnection::class)->verified());
        config(['mail.mailers.smtp.password'=>'changed']); $this->assertFalse(app(MailConnection::class)->verified());
        $this->assertDatabaseCount('commercial_messages',0);
    }
    public function test_unconfirmed_automatic_send_is_not_repeated_and_connection_is_paused(): void
    {
        $this->seed(); $this->smtp(); $this->quote('pending');
        CommunicationRule::where('key','quote_received')->update(['mode'=>'automatic','activated_at'=>now()->subMinute()]);
        $transport=\Mockery::mock(); $transport->shouldReceive('raw')->once()->andThrow(new \RuntimeException('Transport unavailable'));
        Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($transport);
        $this->assertSame(1,app(CommunicationAutomation::class)->process()['blocked']);
        $this->assertSame('uncertain',CommercialMessage::firstOrFail()->status);
        $this->assertFalse(app(MailConnection::class)->verified());
        $this->assertSame(0,app(CommunicationAutomation::class)->process()['sent']);
    }
    public function test_pdf_rejects_remote_logo_and_untrusted_style_input(): void
    {
        $this->seed(); $this->actingAs($this->admin());
        $t=CommunicationTemplate::where('channel','pdf')->where('key','quote')->firstOrFail();
        $this->postJson('/admin/communications/modeles/'.$t->id,['version'=>1,'issuer_name'=>'Maison du Store AM','accent'=>'red; background:url(https://example.invalid)','logo'=>'https://example.invalid/logo.png'])->assertUnprocessable();
        $this->assertSame(1,$t->fresh()->version);
    }
    public function test_old_approval_is_not_automated_after_an_internal_note_edit(): void
    {
        $this->seed(); $quote=$this->quote(); $this->travel(2)->minutes();
        CommunicationRule::where('key','quote')->update(['mode'=>'draft','activated_at'=>now()]);
        $quote->update(['internal_notes'=>'Note interne seulement']);
        $this->assertSame(0,app(CommunicationAutomation::class)->process()['drafts']);
        $quote->update(['status'=>'draft']); $quote->update(['status'=>'approved']);
        $this->assertSame(1,app(CommunicationAutomation::class)->process()['drafts']);
    }
}
