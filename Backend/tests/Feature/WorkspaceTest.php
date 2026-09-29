<?php
namespace Tests\Feature;
use App\Models\{User, Contact, Quote, Appointment, Intervention, Invoice, CommercialMessage, Product};
use App\Services\{CommercialMail, Pricing};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Mail};
use Illuminate\Support\Str;
use Tests\TestCase;
class WorkspaceTest extends TestCase
{
    use RefreshDatabase;
    private function user(string $role = 'admin'): User
    {
        return User::create(['username' => Str::random(10), 'name' => 'Equipe Test', 'email' => Str::random(10).'@example.com', 'password' => 'TemporaryPass123!', 'role' => $role, 'must_change_password' => false]);
    }
    private function contact(): Contact { return Contact::create(['name' => 'Client Test', 'email' => Str::random(8).'@example.com', 'phone' => '+2250700000000']); }
    private function quote(?User $agent = null): Quote
    {
        $contact = $this->contact();
        return Quote::create(['reference' => (string) Str::uuid(), 'type' => 'store', 'contact_id' => $contact->id, 'agent_id' => $agent?->id, 'contact' => ['firstName' => 'Client', 'lastName' => 'Test', 'email' => $contact->email], 'product' => [], 'dimensions' => [], 'lines' => [['description' => 'Pose', 'quantity' => 2, 'unit_price' => 15000]], 'terms_accepted_at' => now()]);
    }
    private function job(User $agent): Intervention { return Intervention::create(['contact_id' => $this->contact()->id, 'agent_id' => $agent->id, 'kind' => 'devis_sur_place', 'scheduled_at' => now()->addDay(), 'address' => 'Abidjan']); }
    public function test_no_public_registration_or_access_to_client_archives(): void
    {
        foreach (['/admin', '/admin/contacts', '/admin/devis', '/admin/messages', '/admin/factures'] as $url) $this->get($url)->assertRedirect('/admin/connexion');
        $this->get('/register')->assertNotFound(); $this->get('/admin/connexion')->assertOk()->assertSee('Accès réservé');
    }
    public function test_login_changes_session_and_requires_personal_password(): void
    {
        $user = $this->user(); $user->update(['must_change_password' => true]);
        $this->post('/admin/connexion', ['username' => $user->username, 'password' => 'TemporaryPass123!'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user); $this->get('/admin')->assertRedirect('/admin/mot-de-passe');
        $this->post('/admin/mot-de-passe', ['current_password' => 'TemporaryPass123!', 'password' => 'MyNewPassword123!', 'password_confirmation' => 'MyNewPassword123!'])->assertRedirect('/admin');
        $this->assertFalse($user->fresh()->must_change_password); $this->assertTrue(Hash::check('MyNewPassword123!', $user->fresh()->password));
    }
    public function test_inactive_staff_and_brute_force_attempts_are_rejected(): void
    {
        $user = $this->user(); $user->update(['active' => false]);
        $this->post('/admin/connexion', ['username' => $user->username, 'password' => 'TemporaryPass123!'])->assertSessionHasErrors(); $this->assertGuest();
        for ($i = 0; $i < 5; $i++) $this->post('/admin/connexion', ['username' => 'unknown', 'password' => 'wrong'])->assertSessionHasErrors();
        $this->post('/admin/connexion', ['username' => 'unknown', 'password' => 'wrong'])->assertSessionHasErrors('username');
    }
    public function test_agent_cannot_read_other_assignments_or_admin_modules(): void
    {
        $agent = $this->user('agent'); $other = $this->user('agent'); $quote = $this->quote($other); $job = $this->job($other);
        $this->actingAs($agent);
        foreach (['contacts','products','factures','messages','equipe'] as $path) $this->get('/admin/'.$path)->assertForbidden();
        $this->get('/admin/devis/'.$quote->id)->assertForbidden();
        $this->get('/admin/devis/'.$quote->id.'/pdf')->assertForbidden();
        $this->put('/admin/devis/'.$quote->id, [])->assertForbidden();
        $this->get('/admin/prestations/'.$job->id)->assertForbidden();
        $this->post('/admin/prestations/'.$job->id.'/devis',['type'=>'store'])->assertForbidden();
        $this->get('/admin/devis')->assertOk()->assertDontSee($quote->contact['email']);
    }
    public function test_admin_pages_render_and_private_content_is_not_cacheable(): void
    {
        $this->actingAs($this->user()); $quote = $this->quote();
        foreach (['','/contacts','/products','/products/create','/devis','/devis/'.$quote->id,'/rendez-vous','/prestations','/factures','/messages','/messages/nouveau','/equipe','/contacts/'.$quote->contact_id] as $path) $this->get('/admin'.$path)->assertOk()->assertHeader('X-Robots-Tag','noindex, nofollow');
        $this->seedProducts(); $this->get('/admin/products/'.Product::first()->id.'/edit')->assertOk();
        $job = $this->job($this->user('agent')); $this->get('/admin/prestations/'.$job->id)->assertOk();
        $appointment = Appointment::create(['reference'=>(string)Str::uuid(),'date'=>'2026-10-01','time'=>'09:00','details'=>['firstName'=>'Client','lastName'=>'Test','appointmentType'=>'showroom']]);
        $this->get('/admin/rendez-vous/'.$appointment->id)->assertOk();
    }
    public function test_agent_creates_onsite_quote_and_cannot_approve_it(): void
    {
        $agent = $this->user('agent'); $job = $this->job($agent); $this->actingAs($agent);
        $this->post('/admin/prestations/'.$job->id.'/devis',['type'=>'store'])->assertRedirect();
        $quote = Quote::firstOrFail(); $this->assertNull($quote->terms_accepted_at);
        $this->post('/admin/prestations/'.$job->id.'/devis',['type'=>'rideau'])->assertRedirect(); $this->assertDatabaseCount('quotes',1);
        $this->put('/admin/devis/'.$quote->id, ['status'=>'accepted','agent_id'=>999,'contact_id'=>999,'valid_until'=>'2026-12-31','tax_basis_points'=>0,'lines'=>[['description'=>'Pose','quantity'=>1,'unit_price'=>45000]]])->assertForbidden();
        $this->assertSame('draft',$quote->fresh()->status); $this->assertSame($agent->id,$quote->fresh()->agent_id);
        $this->get('/admin/devis/'.$quote->id.'/pdf')->assertForbidden();
    }
    public function test_cancelled_appointment_releases_slot_without_losing_history(): void
    {
        $a = Appointment::create(['reference'=>(string)Str::uuid(),'date'=>'2026-10-01','time'=>'09:00','details'=>[]]);
        $this->actingAs($this->user())->put('/admin/rendez-vous/'.$a->id,['date'=>'2026-10-01','time'=>'09:00','status'=>'cancelled'])->assertRedirect();
        $this->assertNull($a->fresh()->reserved_slot);
        Appointment::create(['reference'=>(string)Str::uuid(),'date'=>'2026-10-01','time'=>'09:00','details'=>[]]);
        $this->put('/admin/rendez-vous/'.$a->id,['date'=>'2026-10-01','time'=>'09:00','status'=>'confirmed'])->assertSessionHasErrors('time');
        $this->assertDatabaseCount('appointments',2); $this->assertSame('cancelled',$a->fresh()->status);
    }
    public function test_agent_schedule_conflicts_and_client_document_mismatches_are_rejected(): void
    {
        $agent = $this->user('agent'); $job = $this->job($agent); $this->actingAs($this->user());
        $data=['contact_id'=>$job->contact_id,'agent_id'=>$agent->id,'kind'=>'installation','scheduled_at'=>$job->scheduled_at->format('Y-m-d\TH:i'),'duration_minutes'=>60,'address'=>'Abidjan'];
        $this->post('/admin/prestations',$data)->assertSessionHasErrors('scheduled_at');
        $quote=$this->quote(); $data['quote_id']=$quote->id; $this->post('/admin/prestations',$data)->assertSessionHasErrors('quote_id');
    }
    public function test_agent_can_read_linked_mission_quote_without_pricing_or_edit_rights(): void
    {
        $agent=$this->user('agent'); $owner=$this->user('agent'); $q=$this->quote($owner);
        $q->update(['status'=>'draft','internal_notes'=>'PRIVATE-ADMIN-NOTE']);
        $job=Intervention::create(['contact_id'=>$q->contact_id,'quote_id'=>$q->id,'agent_id'=>$agent->id,'kind'=>'installation','scheduled_at'=>now()->addDay(),'duration_minutes'=>60,'address'=>'Abidjan']);
        $this->actingAs($agent);
        $this->get('/admin/prestations/'.$job->id)->assertOk()->assertSee(route('staff.quotes.show',$q));
        $this->get('/admin/devis/'.$q->id)->assertOk()->assertDontSee('PRIVATE-ADMIN-NOTE')->assertDontSee('FCFA')->assertDontSee('Modifier les choix et mesures');
        $this->get('/admin/devis')->assertOk()->assertSee(route('staff.quotes.show',$q));
        // Sharing the client is insufficient: only this intervention's exact quote is readable.
        $unrelated=$q->replicate(); $unrelated->reference=(string)Str::uuid(); $unrelated->save();
        $this->get('/admin/devis/'.$unrelated->id)->assertForbidden();
        $this->get('/admin/devis')->assertOk()->assertDontSee(route('staff.quotes.show',$unrelated));
        $this->get('/admin/devis?status=approved')->assertOk()->assertDontSee(route('staff.quotes.show',$q));
        $this->get('/admin/devis/'.$q->id.'/formulaire')->assertForbidden();
        $this->put('/admin/devis/'.$q->id.'/formulaire',[])->assertForbidden();
        $this->get('/admin/devis/'.$q->id.'/pdf')->assertForbidden();
        $this->post('/admin/prestations/'.$job->id.'/devis',['type'=>'store'])->assertRedirect('/admin/devis/'.$q->id);
        $this->assertDatabaseCount('quotes',2); $this->assertSame($owner->id,$q->fresh()->agent_id);
        $q->update(['agent_id'=>null]); $this->get('/admin/devis/'.$q->id)->assertOk();
        $job->update(['agent_id'=>$owner->id]); $this->get('/admin/devis/'.$q->id)->assertForbidden();
        $this->get('/admin/devis')->assertOk()->assertDontSee(route('staff.quotes.show',$q));
        $job->update(['agent_id'=>$agent->id,'status'=>'cancelled']); $this->get('/admin/devis/'.$q->id)->assertForbidden();
        $job->update(['status'=>'completed']); $this->get('/admin/devis/'.$q->id)->assertOk();
        $job->update(['contact_id'=>$this->contact()->id]); $this->get('/admin/devis/'.$q->id)->assertForbidden();
        auth()->logout(); session()->flush();
        $this->actingAs($owner); $this->get('/admin/devis/'.$q->id)->assertForbidden();
        $q->update(['agent_id'=>$owner->id]); $this->get('/admin/devis/'.$q->id.'/formulaire')->assertOk();
    }
    public function test_planning_html_form_creates_and_reschedules_with_string_duration(): void
    {
        $agent=$this->user('agent'); $contact=$this->contact(); $this->actingAs($this->user());
        $data=['contact_id'=>(string)$contact->id,'agent_id'=>(string)$agent->id,'kind'=>'installation','scheduled_at'=>'2026-10-05T09:00','duration_minutes'=>'60','address'=>'Abidjan test','quote_id'=>'','appointment_id'=>''];
        $response=$this->post('/admin/prestations',$data)->assertSessionHasNoErrors();
        $job=Intervention::firstOrFail(); $response->assertRedirect('/admin/prestations/'.$job->id);
        $this->assertSame(60,$job->duration_minutes); $this->get('/admin/prestations/'.$job->id)->assertOk();
        $data['scheduled_at']='2026-10-05T10:00'; $data['duration_minutes']='90';
        $this->put('/admin/prestations/'.$job->id,[...$data,'status'=>'planned'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(90,$job->fresh()->duration_minutes);
        $this->assertSame('2026-10-05T10:00',$job->fresh()->scheduled_at->format('Y-m-d\TH:i'));
        $this->post('/admin/prestations',[...$data,'scheduled_at'=>'2026-10-05T11:00'])->assertSessionHasErrors('scheduled_at');
        $this->post('/admin/prestations',[...$data,'scheduled_at'=>'2026-10-05T11:30','duration_minutes'=>'30'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('interventions',2);
        $this->post('/admin/prestations',[...$data,'duration_minutes'=>'15.5'])->assertSessionHasErrors('duration_minutes');
        $this->assertDatabaseCount('interventions',2);
    }
    public function test_unpublished_products_stay_in_archive_and_leave_public_catalogue(): void
    {
        $this->seedProducts(); $product=Product::firstOrFail(); $product->update(['published'=>false]); $this->seedProducts();
        $this->getJson('/api/products')->assertJsonCount(13,'data'); $this->assertDatabaseCount('products',14);
    }
    public function test_invoice_requires_accepted_quote_and_locks_issued_snapshot(): void
    {
        $this->actingAs($this->user()); $quote=$this->quote();
        $this->post('/admin/devis/'.$quote->id.'/facture',['due_date'=>'2026-12-01'])->assertUnprocessable();
        $quote->update(['status'=>'accepted']);
        $this->post('/admin/devis/'.$quote->id.'/facture',['due_date'=>'2026-12-01'])->assertRedirect();
        $invoice=Invoice::firstOrFail();
        $this->post('/admin/factures/'.$invoice->id.'/emettre')->assertSessionHasErrors('issuer');
        config(['workspace.issuer.address'=>'Abidjan','workspace.issuer.registration'=>'Registre test','workspace.issuer.tax_note'=>'Mention test','workspace.issuer.payment_terms'=>'Paiement à 30 jours']);
        $this->post('/admin/factures/'.$invoice->id.'/emettre')->assertRedirect();
        $this->post('/admin/factures/'.$invoice->id.'/emettre')->assertConflict();
        $this->get('/admin/factures/'.$invoice->id)->assertOk();
        $this->get('/admin/factures/'.$invoice->id.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->post('/admin/factures/'.$invoice->id.'/paiement')->assertRedirect();
        $this->assertNotNull($invoice->fresh()->paid_at);
        $this->assertSame(['subtotal'=>30000,'tax'=>5400,'total'=>35400],Pricing::totals($quote->lines,1800));
    }
    public function test_messages_are_drafts_and_mail_is_disabled_until_configured(): void
    {
        $this->actingAs($this->user()); Mail::fake();
        $this->post('/admin/messages',['recipient'=>'client@example.com','subject'=>'Bonjour','body'=>'Message commercial','kind'=>'commercial'])->assertRedirect();
        $message=CommercialMessage::firstOrFail(); $this->assertSame('draft',$message->status);
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertSessionHasErrors('mail');
        $this->assertSame('draft',$message->fresh()->status); Mail::assertNothingSent();
    }
    public function test_promotion_without_consent_cannot_be_sent(): void
    {
        $this->actingAs($this->user()); config(['workspace.mail_enabled'=>true,'mail.default'=>'smtp','mail.mailers.smtp.password'=>'test']);
        $contact=$this->contact(); $message=CommercialMessage::create(['recipient'=>$contact->email,'sender'=>'contact@maisondustoream.com','subject'=>'Offre','body'=>'Promotion','kind'=>'promotion','contact_id'=>$contact->id]);
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertSessionHasErrors('mail');
        $this->assertSame('draft',$message->fresh()->status);
    }
    public function test_document_cannot_be_sent_to_another_customer(): void
    {
        $this->actingAs($this->user()); $quote=$this->quote();
        $this->post('/admin/messages',['recipient'=>'other@example.com','subject'=>'Devis','body'=>'Votre devis','kind'=>'devis','quote_id'=>$quote->id])->assertSessionHasErrors('recipient');
        $this->assertDatabaseCount('commercial_messages',0);
    }
    public function test_incoming_html_is_escaped_and_agents_cannot_send_messages(): void
    {
        $m=CommercialMessage::create(['direction'=>'incoming','status'=>'received','sender'=>'client@example.com','recipient'=>'contact@maisondustoream.com','subject'=>'Test','body'=>'<script>alert(1)</script>']);
        $this->actingAs($this->user())->get('/admin/messages/'.$m->id)->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
        $agent = $this->user('agent');
        $this->withSession(['password_hash_web' => $agent->password])->actingAs($agent)->post('/admin/messages/'.$m->id.'/envoyer')->assertForbidden();
    }
    public function test_send_is_explicit_and_claimed_only_once(): void
    {
        $this->actingAs($this->user()); config(['workspace.mail_enabled'=>true,'mail.default'=>'smtp','mail.mailers.smtp.password'=>'test']);
        $message=CommercialMessage::create(['recipient'=>'client@example.com','sender'=>'contact@maisondustoream.com','subject'=>'Confirmation','body'=>'Bonjour']);
        $transport=\Mockery::mock(); $transport->shouldReceive('raw')->once()->andReturn(null);
        Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($transport);
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertRedirect();
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertConflict();
        $this->assertSame('sent',$message->fresh()->status);
    }
    public function test_transport_failure_is_not_retried_automatically(): void
    {
        $this->actingAs($this->user()); config(['workspace.mail_enabled'=>true,'mail.default'=>'smtp','mail.mailers.smtp.password'=>'test']);
        $message=CommercialMessage::create(['recipient'=>'client@example.com','sender'=>'contact@maisondustoream.com','subject'=>'Test','body'=>'Bonjour']);
        $transport=\Mockery::mock(); $transport->shouldReceive('raw')->once()->andThrow(new \RuntimeException('transport error'));
        Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($transport);
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertSessionHasErrors('mail');
        $this->assertSame('uncertain',$message->fresh()->status);
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertConflict();
    }
    public function test_contact_archive_preserves_requests_and_consent_is_not_inferred(): void
    {
        $quote=$this->quote(); $this->actingAs($this->user());
        $this->put('/admin/contacts/'.$quote->contact_id,['name'=>'Client renommé','kind'=>'client','email'=>$quote->contact['email'],'archived'=>1])->assertRedirect();
        $this->assertNotNull($quote->customer->fresh()->archived_at);
        $this->assertNull($quote->customer->fresh()->marketing_consent_at);
        $this->assertSame('Client',$quote->fresh()->contact['firstName']);
    }
    public function test_imap_import_decodes_text_and_is_idempotent(): void
    {
        config(['workspace.mail_enabled'=>true,'mail.default'=>'smtp','mail.mailers.smtp.password'=>'test']);
        $contact = $this->contact();
        $raw = "From: Client <{$contact->email}>\r\nTo: contact@maisondustoream.com\r\nSubject: Test commercial\r\nDate: Thu, 24 Sep 2026 10:00:00 +0000\r\nMessage-ID: <test@example.com>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nBonjour, je confirme le devis.";
        $message = \Webklex\PHPIMAP\Message::fromString($raw);
        $client = \Mockery::mock(\Webklex\PHPIMAP\Client::class);
        $folder = \Mockery::mock(\Webklex\PHPIMAP\Folder::class);
        $query = \Mockery::mock(\Webklex\PHPIMAP\Query\WhereQuery::class);
        $manager = \Mockery::mock(\Webklex\PHPIMAP\ClientManager::class);
        $manager->shouldReceive('make')->twice()->andReturn($client);
        $client->shouldReceive('connect')->twice(); $client->shouldReceive('disconnect')->twice();
        $client->shouldReceive('getFolderByPath')->with('INBOX')->twice()->andReturn($folder);
        $folder->shouldReceive('examine')->twice()->andReturn(['uidvalidity'=>77]);
        $folder->shouldReceive('query')->twice()->andReturn($query);
        foreach (['all','leaveUnread','setFetchOrderDesc'] as $method) $query->shouldReceive($method)->twice()->andReturnSelf();
        $query->shouldReceive('where')->with('CUSTOM SMALLER',1048576)->twice()->andReturnSelf();
        $query->shouldReceive('limit')->with(20,1)->twice()->andReturnSelf();
        $query->shouldReceive('get')->twice()->andReturn(new \Webklex\PHPIMAP\Support\MessageCollection([$message]));
        $service = new CommercialMail($manager);
        $this->assertSame(1,$service->sync()); $this->assertSame(0,$service->sync());
        $saved = CommercialMessage::firstOrFail();
        $this->assertSame($contact->id,$saved->contact_id); $this->assertStringContainsString('je confirme le devis',$saved->body);
        $this->assertNull($saved->read_at);
    }
    public function test_sent_document_snapshot_survives_later_quote_changes(): void
    {
        $this->actingAs($this->user()); config(['workspace.mail_enabled'=>true,'mail.default'=>'smtp','mail.mailers.smtp.password'=>'test']);
        $quote=$this->quote(); $quote->update(['status'=>'approved']);
        $message=CommercialMessage::create(['recipient'=>$quote->contact['email'],'sender'=>'contact@maisondustoream.com','subject'=>'Devis','body'=>'Bonjour','quote_id'=>$quote->id]);
        $transport=\Mockery::mock(); $transport->shouldReceive('raw')->once()->andReturn(null); Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($transport);
        $this->post('/admin/messages/'.$message->id.'/envoyer')->assertRedirect();
        $quote->update(['lines'=>[['description'=>'Autre version','quantity'=>1,'unit_price'=>1]]]);
        $this->assertSame(15000,$message->fresh()->document_snapshot['lines'][0]['unit_price']);
        $this->get('/admin/messages/'.$message->id.'/document')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->put('/admin/messages/'.$message->id,['subject'=>'Modification','body'=>'Non permise'])->assertConflict();
    }
}
