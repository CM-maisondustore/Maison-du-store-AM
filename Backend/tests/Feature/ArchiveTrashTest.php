<?php

namespace Tests\Feature;

use App\Models\{Appointment, CommercialMessage, Contact, Invoice, Product, Quote, User};
use App\Services\Trash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Archives et corbeille de l'espace pro : listes filtrées, restauration, règles de conservation, purge à 90 jours. */
class ArchiveTrashTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        return User::create(['username' => (string) Str::uuid(), 'name' => ucfirst($role).' Test', 'email' => Str::random(8).'@example.invalid', 'password' => 'TestPassword123!', 'role' => $role, 'must_change_password' => false]);
    }

    private function quote(string $reference = 'AM-TEST-001', ?Contact $contact = null): Quote
    {
        $contact ??= Contact::create(['name' => 'Awa Exemple', 'email' => Str::random(6).'@example.invalid', 'phone' => '+2250700000000']);
        return Quote::create(['reference' => $reference, 'type' => 'store', 'status' => 'pending', 'source' => 'site', 'contact_id' => $contact->id,
            'contact' => ['firstName' => 'Awa', 'lastName' => 'Exemple', 'email' => $contact->email, 'phone' => $contact->phone, 'address' => 'Abidjan'],
            'product' => [], 'dimensions' => [], 'lines' => [], 'tax_basis_points' => 0]);
    }

    public function test_quote_goes_to_trash_leaves_the_list_and_can_be_restored(): void
    {
        $this->actingAs($admin = $this->user());
        $quote = $this->quote();
        $this->get('/admin/devis')->assertOk()->assertSee('AM-TEST-001')->assertSee('Corbeille');
        $this->get(route('staff.quotes.show', $quote))->assertOk()->assertSee('Mettre à la corbeille');

        $this->delete(route('staff.trash.destroy', ['devis', $quote->id]))->assertRedirect(route('staff.quotes.index', ['etat' => 'corbeille']));
        $this->assertSoftDeleted('quotes', ['id' => $quote->id, 'deleted_by' => $admin->id]);
        $this->get('/admin/devis')->assertOk()->assertDontSee('AM-TEST-001');
        $this->get('/admin/devis?etat=corbeille')->assertOk()->assertSee('AM-TEST-001')->assertSee('Restaurer')->assertSee(now()->addDays(90)->format('d/m/Y'));
        $this->get('/admin/corbeille')->assertOk()->assertSee('AM-TEST-001');

        $this->post(route('staff.trash.restore', ['devis', $quote->id]))->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted('quotes', ['id' => $quote->id, 'deleted_by' => null]);
        $this->get('/admin/devis')->assertSee('AM-TEST-001');
    }

    public function test_archived_items_leave_the_current_list_and_queues(): void
    {
        $this->actingAs($this->user());
        $quote = $this->quote();
        $this->post(route('staff.trash.archive', ['devis', $quote->id]))->assertSessionHasNoErrors();
        $this->assertNotNull($quote->fresh()->archived_at);
        $this->get('/admin/devis')->assertDontSee('AM-TEST-001');
        $this->get('/admin/devis?etat=archives')->assertSee('AM-TEST-001');
        $this->get(route('staff.quotes.show', $quote))->assertOk()->assertSee('Désarchiver');
        $this->post(route('staff.trash.unarchive', ['devis', $quote->id]));
        $this->assertNull($quote->fresh()->archived_at);

        // Les six rubriques acceptent les deux onglets.
        foreach (['quotes', 'products', 'contacts', 'appointments', 'messages', 'invoices'] as $list) {
            foreach (['archives', 'corbeille'] as $state) $this->get(route('staff.'.$list.'.index', ['etat' => $state]))->assertOk();
        }
        // Ancien lien « Archivés » des contacts.
        $this->get('/admin/contacts?type=archive')->assertRedirect(route('staff.contacts.index', ['etat' => 'archives']));
    }

    public function test_issued_documents_are_kept_and_only_archived(): void
    {
        $this->actingAs($this->user());
        $quote = $this->quote();
        $invoice = Invoice::create(['quote_id' => $quote->id, 'contact_id' => $quote->contact_id, 'due_date' => now()->addDay(), 'status' => 'issued', 'number' => 'AM-FAC-1', 'snapshot' => ['lines' => [], 'customer' => []]]);
        $this->delete(route('staff.trash.destroy', ['factures', $invoice->id]))->assertSessionHasErrors('trash');
        $this->delete(route('staff.trash.destroy', ['devis', $quote->id]))->assertSessionHasErrors('trash');
        $this->delete(route('staff.trash.destroy', ['clients', $quote->contact_id]))->assertSessionHasErrors('trash');
        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->post(route('staff.trash.archive', ['factures', $invoice->id]))->assertSessionHasNoErrors();
        $this->assertNotNull($invoice->fresh()->archived_at);

        // Une facture brouillon peut aller à la corbeille ; une nouvelle facture du devis la remplace alors.
        $invoice->update(['status' => 'draft', 'number' => null]);
        $this->delete(route('staff.trash.destroy', ['factures', $invoice->id]))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_appointment_in_trash_frees_its_slot(): void
    {
        $this->actingAs($this->user());
        $data = ['reference' => (string) Str::uuid(), 'status' => 'pending', 'date' => now()->addDays(3)->toDateString(), 'time' => '10:00', 'details' => ['firstName' => 'Awa', 'lastName' => 'Exemple']];
        $first = Appointment::create($data);
        $this->delete(route('staff.trash.destroy', ['rendez-vous', $first->id]))->assertSessionHasNoErrors();
        $this->assertNull(Appointment::withTrashed()->find($first->id)->reserved_slot);
        $second = Appointment::create([...$data, 'reference' => (string) Str::uuid()]);
        $this->assertNotNull($second->reserved_slot);
        // Le créneau est repris : la restauration est refusée sans rien casser.
        $this->post(route('staff.trash.restore', ['rendez-vous', $first->id]))->assertSessionHasErrors('trash');
        $this->assertSoftDeleted('appointments', ['id' => $first->id]);
        $second->update(['time' => '11:00']);
        $this->post(route('staff.trash.restore', ['rendez-vous', $first->id]))->assertSessionHasNoErrors();
        $this->assertSame($data['date'].' 10:00', $first->fresh()->reserved_slot);
    }

    public function test_items_are_purged_after_ninety_days_except_legacy_removals(): void
    {
        $admin = $this->user();
        $contact = Contact::create(['name' => 'Ancien client', 'email' => 'ancien@example.invalid']);
        $quote = $this->quote('AM-OLD-001', $contact);
        CommercialMessage::create(['contact_id' => $contact->id, 'quote_id' => $quote->id, 'direction' => 'outgoing', 'status' => 'draft', 'sender' => 'a@example.invalid', 'recipient' => 'b@example.invalid', 'subject' => 'Devis', 'body' => 'Bonjour']);
        Trash::moveToTrash($quote, $admin->id);
        Trash::moveToTrash($contact, $admin->id);
        $recent = $this->quote('AM-NEW-001');
        Trash::moveToTrash($recent, $admin->id);
        $legacy = Product::create(['reference' => 'LEGACY-1', 'details' => ['name' => 'Ancien tissu', 'type' => 'rideau'], 'published' => false]);
        $legacy->delete(); // retrait technique d'une ancienne migration : pas d'auteur

        $this->travel(91)->days();
        Quote::withTrashed()->whereKey($recent->id)->update(['deleted_at' => now()->subDays(10)]);
        $this->assertSame(2, Trash::purgeExpired());
        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('commercial_messages', ['subject' => 'Devis', 'quote_id' => null, 'contact_id' => null]);
        $this->assertDatabaseHas('quotes', ['id' => $recent->id]);
        $this->assertDatabaseHas('products', ['id' => $legacy->id]);
        $this->actingAs($admin)->get(route('staff.products.index', ['etat' => 'corbeille']))->assertOk()->assertDontSee('LEGACY-1');
    }

    public function test_agents_cannot_archive_or_see_the_trash(): void
    {
        $agent = $this->user('agent');
        $quote = $this->quote();
        $quote->update(['created_by' => $agent->id]);
        $this->actingAs($agent);
        $this->post(route('staff.trash.archive', ['devis', $quote->id]))->assertForbidden();
        $this->delete(route('staff.trash.destroy', ['devis', $quote->id]))->assertForbidden();
        $this->get('/admin/corbeille')->assertForbidden();
        $this->get('/admin/devis?etat=corbeille')->assertOk()->assertSee('AM-TEST-001')->assertDontSee('Restaurer');
    }

    public function test_trashed_contact_is_restored_instead_of_duplicated_by_a_new_quote(): void
    {
        $admin = $this->user();
        $contact = Contact::create(['name' => 'Awa Exemple', 'email' => 'awa@example.invalid']);
        Trash::moveToTrash($contact, $admin->id);
        $found = app(\App\Services\ContactArchive::class)->capture(['firstName' => 'Awa', 'lastName' => 'Exemple', 'phone' => '+2250700000000', 'email' => 'AWA@example.invalid']);
        $this->assertSame($contact->id, $found->id);
        $this->assertNotSoftDeleted('contacts', ['id' => $contact->id]);
    }
}
