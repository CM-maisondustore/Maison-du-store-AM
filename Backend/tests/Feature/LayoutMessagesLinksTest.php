<?php

namespace Tests\Feature;

use App\Models\{CommercialMessage, CommunicationTemplate, Contact, Quote, SharedLinkEvent, SiteVisit, User};
use App\Services\{CommunicationTemplates, DocumentBlocks, DocumentLayout, DocumentPreview, DocumentViewModel, Documents, MdsTemplates};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Mise en page (contenus des blocs, polices, devis particulier), modèles de messages, suivi des liens transmis. */
class LayoutMessagesLinksTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => (string) Str::uuid(), 'name' => 'Admin', 'email' => Str::random(8).'@example.invalid', 'password' => 'TestPassword123!', 'role' => 'admin', 'must_change_password' => false]);
    }

    private function settings(int $version, array $extra = []): array
    {
        return [...DocumentLayout::defaults(), 'version' => $version, 'name' => 'Devis', 'body' => 'Introduction propre au devis', 'issuer_name' => 'Maison du Store AM',
            'issuer_address' => 'Abidjan', 'issuer_registration' => '', 'issuer_tax_note' => '', 'issuer_payment_terms' => '', 'scenario' => 'single', 'preview_type' => 'quote', ...$extra];
    }

    private function quote(): Quote
    {
        $data = DocumentPreview::data('quote', 'four', DocumentLayout::defaults());
        // Lignes enregistrées par le chiffrage : « description » (les données d'aperçu utilisent « label »).
        foreach ($data['financial']['openings'] as &$opening) foreach ($opening['lines'] as &$line) { $line['description'] = $line['label'] ?? 'Prestation'; }
        unset($opening, $line);
        foreach ($data['financial']['fees'] as &$fee) { $fee['description'] = $fee['label'] ?? 'Pose et livraison'; }
        unset($fee);
        $data['customer']['phone'] = '+2250700000000';
        return Quote::create(['reference' => 'AM-LAYOUT-01', 'type' => 'rideau', 'status' => 'draft', 'contact' => $data['customer'], 'product' => [], 'dimensions' => [],
            'configuration' => ['calculation' => ['financial' => $data['financial']]], 'lines' => [], 'tax_basis_points' => 0, 'deposit_amount' => 190313, 'payment_mode' => 'advance', 'valid_until' => '2026-12-01']);
    }

    public function test_blocks_accept_labels_complements_and_new_fonts(): void
    {
        $starter = DocumentBlocks::starter();
        foreach ($starter['items'] as &$item) {
            if ($item['type'] === 'client') $item = [...$item, 'label' => 'Bénéficiaire', 'text' => 'Chantier : {client.telephone}', 'font' => 'Times New Roman'];
            if ($item['type'] === 'total') $item = [...$item, 'label' => 'Net à payer', 'font' => 'Arial'];
            if ($item['type'] === 'lines') $item = [...$item, 'label' => 'Article', 'text' => 'ignoré'];
        }
        unset($item);
        $blocks = DocumentBlocks::normalize(json_encode($starter));
        $client = collect($blocks['items'])->firstWhere('type', 'client');
        $this->assertSame(['Bénéficiaire', 'Chantier : {client.telephone}', 'Times New Roman'], [$client['label'], $client['text'], $client['font']]);
        $this->assertArrayNotHasKey('text', collect($blocks['items'])->firstWhere('type', 'lines'), 'Le tableau n’a pas de texte complémentaire.');

        $style = [...DocumentLayout::defaults(), 'font_family' => 'Arial', 'blocks' => $blocks];
        $html = view('documents.blocks', DocumentViewModel::prepare(DocumentPreview::data('quote', 'four', $style)))->render();
        foreach (['Bénéficiaire', 'Net à payer', '>Article<', 'Chantier : ', '"Times New Roman", "Times", serif', '"Arial", "Helvetica", sans-serif'] as $needle) $this->assertStringContainsString($needle, html_entity_decode($html));
        $pdf = app(Documents::class)->render(DocumentPreview::data('quote', 'four', $style));
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(['DejaVu Sans', 'DejaVu Serif', 'Arial', 'Times New Roman'], DocumentBlocks::FONTS);
    }

    public function test_one_quote_gets_its_own_layout_before_sending(): void
    {
        $this->seed();
        $this->actingAs($this->admin());
        $quote = $this->quote();
        $this->get(route('staff.messages.create', ['quote_id' => $quote->id]))->assertOk()
            ->assertSee(route('staff.quotes.layout', $quote), false)->assertSee(route('staff.quotes.pdf', [$quote, 'apercu' => 1]), false);
        $this->get(route('staff.quotes.pdf', [$quote, 'apercu' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="apercu-AM-LAYOUT-01.pdf"');

        $template = CommunicationTemplate::where('channel', 'pdf')->where('key', 'quote')->firstOrFail();
        $this->get(route('staff.quotes.layout', $quote))->assertOk()->assertSee('Vous modifiez la mise en page de ce devis uniquement')->assertSee('Enregistrer pour ce devis')->assertDontSee('Publier le modèle');
        $before = $template->fresh()->only(['version', 'options', 'body']);

        $this->post(route('staff.quotes.layout.preview', $quote), $this->settings($template->version, ['font_family' => 'Times New Roman']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->post(route('staff.quotes.layout.save', $quote), $this->settings($template->version, ['edit_version' => $quote->edit_version ?? 0, 'font_family' => 'Times New Roman', 'accent' => '#123456']))
            ->assertSessionHasNoErrors()->assertRedirect(route('staff.quotes.layout', $quote));
        $quote->refresh();
        $this->assertSame('Times New Roman', $quote->document_metadata['presentation_override']['options']['font_family']);
        $this->assertSame($before, $template->fresh()->only(['version', 'options', 'body']), 'Le modèle commun ne change pas.');
        $data = app(Documents::class)->quoteData($quote);
        $this->assertSame(['#123456', 'Introduction propre au devis', true], [$data['presentation']['accent'], $data['presentation']['introduction'], $data['presentation']['quote_override']]);
        $this->assertArrayNotHasKey('presentation_override', $data);

        // La présentation (objet, pièces) garde la mise en page propre ; « Reprendre le modèle commun » la retire.
        $this->post(route('staff.layouts.metadata', $quote), ['edit_version' => $quote->edit_version, 'object' => 'Salon', 'rooms' => []])->assertSessionHasNoErrors();
        $this->assertArrayHasKey('presentation_override', $quote->fresh()->document_metadata);
        $this->delete(route('staff.quotes.layout.reset', $quote))->assertRedirect();
        $this->assertArrayNotHasKey('presentation_override', $quote->fresh()->document_metadata);
        $this->assertSame('Salon', $quote->fresh()->document_metadata['object']);

        $quote->update(['status' => 'accepted']);
        $this->get(route('staff.quotes.layout', $quote))->assertStatus(409);
    }

    public function test_messages_are_professional_with_site_links_except_document_links(): void
    {
        config(['app.url' => 'https://maisondustoream.com']);
        [, $waQuote] = MdsTemplates::message('quote', 'whatsapp');
        $this->assertStringNotContainsString('{{site}}', $waQuote, 'Le message WhatsApp du devis porte déjà le lien du document.');
        $this->assertStringNotContainsString('{{lien_', $waQuote);
        foreach (['quote_received', 'appointment', 'promotion', 'commercial'] as $key) {
            foreach (['email', 'whatsapp'] as $channel) {
                [, $body] = MdsTemplates::message($key, $channel);
                $this->assertMatchesRegularExpression('/\{\{(site|lien_[a-z_]+)\}\}/', $body, "$key / $channel : lien vers le site attendu.");
                CommunicationTemplates::validateText($body);
                $this->assertLessThanOrEqual(4, preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{23F0}]/u', $body), "$key / $channel : emojis discrets.");
            }
        }
        $draft = CommunicationTemplates::draft('quote_received', [...CommunicationTemplates::context(), 'prenom' => 'Awa', 'reference' => 'AM-1'], 'whatsapp');
        $this->assertStringContainsString('https://maisondustoream.com/?utm_source=whatsapp&utm_medium=message&utm_campaign=quote_received#realisations', $draft['body']);
        $this->assertStringContainsString('🌐 maisondustoream.com', $draft['body']);
        [, $email] = MdsTemplates::message('quote', 'email');
        $this->assertStringContainsString('{{lien_realisations}}', $email, 'L’e-mail joint le PDF : il peut porter un lien vers le site.');
    }

    public function test_visitors_page_follows_transmitted_links(): void
    {
        $this->seed();
        $admin = $this->admin();
        $this->actingAs($admin);
        $quote = $this->quote();
        $contact = Contact::create(['name' => 'Awa Exemple', 'phone' => $quote->contact['phone']]);
        $quote->update(['contact_id' => $contact->id, 'status' => 'approved']);
        $this->post('/admin/messages', ['channel' => 'whatsapp', 'recipient' => $quote->contact['phone'], 'subject' => 'Votre devis', 'body' => 'Bonjour', 'kind' => 'devis', 'quote_id' => $quote->id, 'contact_id' => $contact->id])->assertSessionHasNoErrors();
        $message = CommercialMessage::firstOrFail();
        $location = $this->get('/admin/messages/'.$message->id.'/whatsapp')->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/', (string) $location, (string) $location.' '.json_encode(session('errors')?->all()));
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $link = substr($query['text'], strpos($query['text'], 'http'));
        auth()->logout();

        // L'aperçu automatique de WhatsApp ne compte pas ; le client ouvre puis télécharge.
        $this->get($link, ['User-Agent' => 'WhatsApp/2.24.1 A'])->assertOk();
        $page = $this->get($link, ['User-Agent' => 'Mozilla/5.0 (iPhone) Mobile Safari'])->assertOk()->getContent();
        preg_match('#href="([^"]+/pdf\?[^"]*telecharger=1[^"]*)"#', $page, $m);
        $this->get(html_entity_decode($m[1]), ['User-Agent' => 'Mozilla/5.0 (iPhone) Mobile Safari'])->assertOk();
        $this->travel(31)->days();
        $this->get($link, ['User-Agent' => 'Mozilla/5.0 (Android) Mobile'])->assertStatus(410);
        $this->travelBack();
        $this->assertSame(['downloaded' => 1, 'expired' => 1, 'opened' => 1], SharedLinkEvent::pluck('event')->countBy()->sortKeys()->all());
        $this->assertSame('téléphone', SharedLinkEvent::where('event', 'opened')->value('device'));

        SiteVisit::create(['visitor_key' => 'v', 'session_key' => 's', 'origin' => 'whatsapp', 'campaign' => 'utm_source=whatsapp&utm_medium=message&utm_campaign=quote_received',
            'arrived_at' => now(), 'last_activity_at' => now(), 'page_views' => 1]);
        $this->actingAs($admin)->get('/admin/visiteurs')->assertOk()
            ->assertSee('Liens transmis aux clients')->assertSee('Awa Exemple')->assertSee('Ouvert 1 fois')->assertSee('PDF téléchargé')->assertSee('Lien expiré consulté')
            ->assertSee('Demande de devis reçue, WhatsApp');
    }
}
