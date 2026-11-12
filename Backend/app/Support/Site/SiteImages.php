<?php

namespace App\Support\Site;

use App\Models\SiteContent;
use Illuminate\Support\Collection;

/**
 * Images fixes du site, administrées dans « Images et portfolio ».
 * Même logique que le portfolio : une fiche SiteContent par emplacement (slot) ; si elle est visible et porte
 * une image importée, le site l'affiche, sinon il garde l'image d'origine livrée avec le site.
 */
final class SiteImages
{
    /** slot => [kind, rubrique, emplacement, image d'origine, texte alternatif d'origine]. */
    public const SLOTS = [
        'hero' => ['hero', 'Accueil', 'Bandeau d’accueil · Stores & Rideaux', 'site/images/realisations/habillage-salon.webp', 'Salon habillé de rideaux et voilages sur mesure'],
        'hero-electro' => ['hero', 'Accueil', 'Bandeau d’accueil · Électroménager', 'site/images/soon/electro-hero.jpg', ''],
        'hero-meubles' => ['hero', 'Accueil', 'Bandeau d’accueil · Meubles', 'site/images/soon/meubles-hero.jpg', ''],
        'boutique-stores' => ['boutique', 'Accueil · Nos boutiques', 'Carte Stores & Rideaux', 'site/images/realisations/rideaux-occultants.webp', ''],
        'boutique-electro' => ['boutique', 'Accueil · Nos boutiques', 'Carte Électroménager', 'site/images/soon/electro.jpg', ''],
        'boutique-meubles' => ['boutique', 'Accueil · Nos boutiques', 'Carte Meubles', 'site/images/soon/meubles.jpg', ''],
        'menu-rideaux' => ['menu', 'Menu MDS-Shop', 'Image du menu Rideaux & voilages', 'site/images/realisations/wave-luxe.jpg', ''],
        'menu-stores' => ['menu', 'Menu MDS-Shop', 'Image du menu Stores', 'site/images/realisations/store-zebra.webp', ''],
        'contact' => ['contact', 'Accueil · Contact', 'Photo du showroom', 'site/images/realisations/showroom.webp', 'Showroom Maison du Store AM à Cocody'],
    ];

    /**
     * Format affiché sur le site pour une fiche (proportions CSS de mds.css), proposé par défaut au recadrage.
     * @return array{0:int,1:int,2:string} largeur, hauteur, description
     */
    public static function aspect(SiteContent $c): array
    {
        $kind = self::SLOTS[$c->slot][0] ?? $c->kind;
        return match (true) {
            in_array($kind, ['hero', 'boutique'], true) => [16, 10, 'bandeau et cartes de l’accueil'],
            $kind === 'quote' && !str_starts_with((string) $c->slot, 'finish-') => [16, 9, 'carte du devis en ligne'],
            default => [4, 3, $kind === 'portfolio' ? 'réalisations de l’accueil' : 'format de l’emplacement'],
        };
    }

    /** Fiches publiées des emplacements fixes, chargées une fois par requête (attribut de la requête, pas de cache statique). */
    private static function rows(): Collection
    {
        $attributes = request()->attributes;
        if (!$attributes->has('site_images')) {
            $attributes->set('site_images', SiteContent::with('media')->whereIn('slot', array_keys(self::SLOTS))->where('published', true)->get()->keyBy('slot'));
        }
        return $attributes->get('site_images');
    }

    public static function flush(): void { request()->attributes->remove('site_images'); }

    /** URL affichée sur le site : image importée, sinon image d'origine. */
    public static function url(string $slot): string
    {
        $row = self::rows()->get($slot);
        return $row?->media ? $row->media->url() : asset(self::SLOTS[$slot][3]);
    }

    public static function alt(string $slot): string
    {
        $row = self::rows()->get($slot);
        return ($row?->media ? $row->image_alt : null) ?: self::SLOTS[$slot][4];
    }

    public static function fallback(string $slot): ?string
    {
        return isset(self::SLOTS[$slot]) ? asset(self::SLOTS[$slot][3]) : null;
    }

    /** Crée les fiches manquantes (sans image : le site garde l'image d'origine). */
    public static function ensure(): void
    {
        $existing = SiteContent::whereIn('slot', array_keys(self::SLOTS))->pluck('slot')->all();
        foreach (array_diff(array_keys(self::SLOTS), $existing) as $slot) {
            [$kind, , $label, , $alt] = self::SLOTS[$slot];
            SiteContent::create(['slot' => $slot, 'kind' => $kind, 'title' => $label, 'image_alt' => $alt, 'published' => true, 'position' => 0]);
        }
        self::flush();
    }

    /**
     * Image d'origine d'une réalisation sans image importée : le site reprend les réalisations livrées,
     * dans l'ordre d'affichage des fiches visibles (même règle que la page d'accueil).
     *
     * @return array<int, string> id de fiche => URL
     */
    public static function portfolioFallbacks(Collection $portfolio): array
    {
        $fallback = config('site.realisations');
        $urls = [];
        foreach ($portfolio->where('published', true)->sortBy([['position', 'asc'], ['id', 'asc']])->values() as $i => $c) {
            if (!$c->media_asset_id && isset($fallback[$i])) $urls[$c->id] = asset($fallback[$i]['img']);
        }
        return $urls;
    }
}
