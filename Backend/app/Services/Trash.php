<?php

namespace App\Services;

use App\Models\{Appointment, CommercialMessage, Contact, Invoice, Product, Quote};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{Cache, DB, Log};

/**
 * Archives et corbeille de l'espace pro. Un élément mis à la corbeille reste restaurable 90 jours,
 * puis il est supprimé définitivement (purge quotidienne). Les pièces comptables émises ne vont jamais à la corbeille.
 */
final class Trash
{
    public const DAYS = 90;

    /** Type d'URL => [modèle, libellé de la rubrique, route de la liste, route de la fiche]. */
    public const TYPES = [
        'devis' => [Quote::class, 'Devis', 'quotes.index', 'quotes.show'],
        'catalogue' => [Product::class, 'Catalogue', 'products.index', 'products.edit'],
        'clients' => [Contact::class, 'Clients et contacts', 'contacts.index', 'contacts.show'],
        'rendez-vous' => [Appointment::class, 'Rendez-vous', 'appointments.index', 'appointments.show'],
        'messages' => [CommercialMessage::class, 'Messagerie', 'messages.index', 'messages.show'],
        'factures' => [Invoice::class, 'Factures', 'invoices.index', 'invoices.show'],
    ];

    /** Références vers l'élément à vider avant la suppression définitive (colonnes facultatives). */
    private const DETACH = [
        'devis' => [['interventions', 'quote_id'], ['commercial_messages', 'quote_id'], ['document_issues', 'quote_id']],
        'clients' => [['quotes', 'contact_id'], ['appointments', 'contact_id'], ['commercial_messages', 'contact_id']],
        'rendez-vous' => [['interventions', 'appointment_id'], ['commercial_messages', 'appointment_id']],
        'messages' => [['document_archives', 'commercial_message_id']],
        'factures' => [['commercial_messages', 'invoice_id'], ['document_archives', 'invoice_id']],
        'catalogue' => [],
    ];

    /** État demandé par ?etat= : '' (en cours), 'archives' ou 'corbeille' ; réservé aux admins. */
    public static function state(\Illuminate\Http\Request $r): string
    {
        $state = (string) $r->query('etat', '');
        return $r->user()?->isAdmin() && in_array($state, ['archives', 'corbeille'], true) ? $state : '';
    }

    /** Ordre de la corbeille : derniers supprimés en premier. */
    public static function order(\Illuminate\Database\Eloquent\Builder $query, string $state): \Illuminate\Database\Eloquent\Builder
    {
        return $state === 'corbeille' ? $query->reorder()->with('deletedBy')->latest('deleted_at') : $query;
    }

    public static function typeOf(Model $model): string
    {
        foreach (self::TYPES as $type => [$class]) if ($model instanceof $class) return $type;
        throw new \InvalidArgumentException('Type non pris en charge.');
    }

    public static function find(string $type, int $id, bool $trashed = false): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $query = self::TYPES[$type][0]::query();
        return ($trashed ? $query->inTrash() : $query)->findOrFail($id);
    }

    public static function title(Model $m): string
    {
        $name = fn (?array $c) => trim(($c['firstName'] ?? '').' '.($c['lastName'] ?? ''));
        return match (true) {
            $m instanceof Quote => trim(StaffInterface::ref($m->reference).' · '.$name($m->contact), ' ·'),
            $m instanceof Product => trim($m->reference.' · '.($m->details['name'] ?? ''), ' ·'),
            $m instanceof Contact => $m->name,
            $m instanceof Appointment => trim(\Illuminate\Support\Carbon::parse($m->date)->format('d/m/Y').' '.$m->time.' · '.$name($m->details), ' ·'),
            $m instanceof CommercialMessage => trim($m->subject.' · '.($m->direction === 'incoming' ? $m->sender : $m->recipient), ' ·'),
            $m instanceof Invoice => trim(($m->number ?: 'Facture brouillon').' · '.($m->snapshot['quote_reference'] ?? ''), ' ·'),
        };
    }

    /** Raison qui empêche la mise à la corbeille, ou null. */
    public static function blocker(Model $m): ?string
    {
        if ($m instanceof Invoice && $m->status !== 'draft') return 'Une facture émise doit être conservée : archivez-la plutôt.';
        if ($m instanceof Quote) {
            if (Invoice::withTrashed()->where('quote_id', $m->id)->exists()) return 'Ce devis a une facture : archivez-le plutôt (ou mettez d’abord la facture brouillon à la corbeille).';
            if ($m->payments()->exists()) return 'Ce devis a des paiements enregistrés : archivez-le plutôt.';
        }
        if ($m instanceof Contact) {
            if (Invoice::withTrashed()->where('contact_id', $m->id)->exists()) return 'Ce client a des factures : archivez-le plutôt.';
            if (DB::table('interventions')->where('contact_id', $m->id)->exists()) return 'Ce client a des prestations : archivez-le plutôt.';
        }
        return null;
    }

    public static function moveToTrash(Model $m, int $userId): void
    {
        DB::transaction(function () use ($m, $userId) {
            $m->forceFill(['deleted_by' => $userId]);
            // Un rendez-vous supprimé libère son créneau.
            if ($m instanceof Appointment) $m->forceFill(['reserved_slot' => null]);
            $m->saveQuietly();
            $m->delete();
        });
    }

    /** Restaure ; renvoie un message d'erreur si le créneau d'un rendez-vous est déjà pris. */
    public static function restore(Model $m): ?string
    {
        try {
            DB::transaction(function () use ($m) {
                $m->restore();
                $m->forceFill(['deleted_by' => null]);
                $m->save(); // recalcule le créneau réservé d'un rendez-vous
            });
        } catch (QueryException) {
            return 'Le créneau de ce rendez-vous est déjà pris : modifiez d’abord l’autre rendez-vous.';
        }
        return null;
    }

    /** Suppression définitive ; false si l'élément est encore lié à des pièces à conserver. */
    public static function purge(Model $m): bool
    {
        $type = self::typeOf($m);
        try {
            DB::transaction(function () use ($m, $type) {
                foreach (self::DETACH[$type] as [$table, $column]) DB::table($table)->where($column, $m->getKey())->update([$column => null]);
                $m->forceDelete();
            });
            return true;
        } catch (QueryException $e) {
            Log::warning('Corbeille : suppression définitive impossible', ['type' => $type, 'id' => $m->getKey(), 'error' => $e->getMessage()]);
            return false;
        }
    }

    /** Supprime définitivement les éléments en corbeille depuis plus de 90 jours. */
    public static function purgeExpired(): int
    {
        $count = 0;
        foreach (self::TYPES as [$class]) {
            $class::inTrash()->where('deleted_at', '<', now()->subDays(self::DAYS))->orderBy('id')->limit(200)->get()
                ->each(function (Model $m) use (&$count) { if (self::purge($m)) $count++; });
        }
        return $count;
    }

    /** Purge au plus une fois par jour, appelée depuis l'espace pro : rien ne dépend d'une tâche planifiée. */
    public static function purgeDaily(): void
    {
        if (Cache::add('trash:purge', 1, now()->addDay())) self::purgeExpired();
    }

    /** Compteurs des onglets d'une liste : archivés et corbeille. */
    public static function counts(string $type, ?\Closure $scope = null): array
    {
        $class = self::TYPES[$type][0];
        $q = fn () => $scope ? $scope($class::query()) : $class::query();
        return ['archives' => $q()->archived()->count(), 'corbeille' => $q()->inTrash()->count()];
    }
}
