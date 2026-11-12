<?php

namespace App\Models\Concerns;

use App\Services\Trash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Archives et corbeille (le modèle utilise aussi SoftDeletes).
 * Archivé : hors des listes courantes, toujours consultable. Corbeille : supprimé, restaurable pendant 90 jours.
 */
trait Archivable
{
    public function initializeArchivable(): void
    {
        $this->mergeCasts(['archived_at' => 'datetime']);
    }

    public function scopeUnarchived(Builder $query): Builder { return $query->whereNull($this->qualifyColumn('archived_at')); }
    public function scopeArchived(Builder $query): Builder { return $query->whereNotNull($this->qualifyColumn('archived_at')); }
    /** Éléments mis à la corbeille depuis l'espace pro (les anciens retraits techniques n'ont pas d'auteur). */
    public function scopeInTrash(Builder $query): Builder { return $query->onlyTrashed()->whereNotNull($this->qualifyColumn('deleted_by')); }

    /** État de liste : '' (en cours), 'archives' ou 'corbeille'. */
    public function scopeListState(Builder $query, ?string $state): Builder
    {
        return match ($state) { 'archives' => $query->archived(), 'corbeille' => $query->inTrash(), default => $query->unarchived() };
    }

    public function deletedBy() { return $this->belongsTo(\App\Models\User::class, 'deleted_by'); }
    public function isArchived(): bool { return $this->archived_at !== null; }
    public function purgeDate(): ?Carbon { return $this->deleted_at?->copy()->addDays(Trash::DAYS); }
}
