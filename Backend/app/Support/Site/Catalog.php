<?php

namespace App\Support\Site;

use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

/**
 * Catalogue public Stores & Rideaux, lu directement dans les produits de l'espace pro.
 * Seuls les produits publiés, de l'univers Stores & Rideaux, sont montrés. Aucun coût n'est exposé.
 */
class Catalog
{
    public const PER_PAGE = 24;

    public const SORTS = ['pert' => 'Pertinence', 'az' => 'Nom, de A à Z', 'dispo' => 'Disponibles d’abord'];

    /** Groupes de filtres proposés dans une boutique. */
    public const GROUPS = ['Type', 'Fonction', 'Couleur', 'Disponibilité'];

    public static function visible(): bool
    {
        return SiteSetting::catalogueVisible();
    }

    /** Clé de boutique publique pour un type de produit de l'espace pro. */
    public static function shopFor(string $type): ?string
    {
        return ['rideau' => 'rideaux', 'store' => 'stores'][$type] ?? null;
    }

    /** @return Collection<int, object> */
    public static function all(): Collection
    {
        if (! self::visible()) {
            return collect();
        }

        return Product::with('media')->where('published', true)->orderBy('id')->get()
            ->filter(fn (Product $p) => self::shopFor($p->details['type'] ?? '') && ($p->details['universe'] ?? 'stores_rideaux') === 'stores_rideaux')
            ->map(fn (Product $p) => self::present($p))
            ->values();
    }

    public static function forShop(?string $shop): Collection
    {
        return self::all()->when($shop, fn ($c) => $c->where('shop', $shop)->values());
    }

    public static function find(string $reference): ?object
    {
        return self::all()->firstWhere('ref', $reference);
    }

    /**
     * Menu MDS-Shop construit à partir des produits publiés de l'espace pro.
     * Rideaux : « Rideaux occultants » pour les produits marqués occultants, « Voilages » pour les autres.
     *
     * @return array<string, list<array{label: ?string, items: list<array{name: string, href: string}>}>>
     */
    public static function menu(): array
    {
        $link = fn ($p) => ['name' => $p->name, 'href' => route('site.product', $p->ref)];
        $all = self::all();
        $rideaux = $all->where('shop', 'rideaux');
        $occultant = config('site.features.occultant');

        return [
            'rideaux' => array_values(array_filter([
                ['label' => 'Rideaux occultants', 'items' => $rideaux->filter(fn ($p) => in_array($occultant, $p->features, true))->map($link)->values()->all()],
                ['label' => 'Voilages', 'items' => $rideaux->reject(fn ($p) => in_array($occultant, $p->features, true))->map($link)->values()->all()],
            ], fn ($g) => $g['items'])),
            'stores' => array_values(array_filter([
                ['label' => null, 'items' => $all->where('shop', 'stores')->map($link)->values()->all()],
            ], fn ($g) => $g['items'])),
        ];
    }

    public static function present(Product $p): object
    {
        $d = $p->details ?? [];
        $colors = config('site.colors');
        $features = config('site.features');

        return (object) [
            'id' => $p->id,
            'ref' => $p->reference,
            'model' => 'product-'.$p->id,
            'shop' => self::shopFor($d['type'] ?? ''),
            'kind' => $d['type'] ?? '',
            'name' => $d['name'] ?? $p->reference,
            'type' => $d['category'] ?? '',
            'description' => $d['description'] ?? '',
            'image' => $p->media?->url(),
            'imageAlt' => $p->image_alt ?: ($d['name'] ?? ''),
            'available' => (bool) ($d['available'] ?? false),
            'featured' => (bool) ($d['featured'] ?? false),
            'colors' => collect($d['colors'] ?? [])->filter(fn ($c) => isset($colors[$c]))->map(fn ($c) => $colors[$c])->values()->all(),
            'features' => collect($d['features'] ?? [])->filter(fn ($f) => isset($features[$f]))->map(fn ($f) => $features[$f])->values()->all(),
            'specs' => collect($d['specs'] ?? [])->map(fn ($s) => [$s['label'] ?? '', $s['value'] ?? ''])->filter(fn ($s) => $s[0] !== '')->values()->all(),
        ];
    }

    /** Valeurs d'un groupe de filtres pour un produit. */
    public static function values(object $p, string $group): array
    {
        return match ($group) {
            'Type' => array_filter([$p->type]),
            'Fonction' => $p->features,
            'Couleur' => array_column($p->colors, 0),
            'Disponibilité' => [$p->available ? 'Disponible' : 'Bientôt de retour'],
            default => [],
        };
    }

    /**
     * Filtre « OU dans un groupe, ET entre les groupes », comme la maquette.
     *
     * @param  array<string, list<string>>  $active
     */
    public static function matches(object $p, array $active, string $q, ?string $skip = null): bool
    {
        foreach ($active as $group => $values) {
            if ($group !== $skip && ! array_intersect($values, self::values($p, $group))) {
                return false;
            }
        }

        return $q === '' || str_contains(mb_strtolower($p->name.' '.$p->type.' '.$p->description), mb_strtolower($q));
    }

    public static function sort(Collection $items, string $sort): Collection
    {
        return match ($sort) {
            'az' => $items->sortBy(fn ($p) => mb_strtolower($p->name))->values(),
            'dispo' => $items->sortByDesc(fn ($p) => $p->available)->values(),
            default => $items->sortByDesc(fn ($p) => $p->featured)->values(),
        };
    }

    /** Groupes affichés avec le nombre de produits par option. */
    public static function groups(Collection $base, array $active, string $q): array
    {
        $hex = [];
        foreach ($base as $p) {
            foreach ($p->colors as [$n, $h]) {
                $hex[$n] = $h;
            }
        }

        $out = [];
        foreach (self::GROUPS as $group) {
            $values = $base->flatMap(fn ($p) => self::values($p, $group))->unique()->values();
            if ($values->count() < 2) {
                continue;
            }
            $out[] = [
                'label' => $group,
                'count' => count($active[$group] ?? []),
                'options' => $values->map(fn (string $v) => [
                    'label' => $v,
                    'on' => in_array($v, $active[$group] ?? [], true),
                    'count' => $base->filter(fn ($p) => self::matches($p, $active, $q, $group) && in_array($v, self::values($p, $group), true))->count(),
                    'hex' => $group === 'Couleur' ? ($hex[$v] ?? '#ccc') : null,
                ])->all(),
            ];
        }

        return $out;
    }
}
