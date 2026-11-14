<?php

namespace App\Support\Site;

use App\Http\Controllers\Site\SiteController;
use Illuminate\Support\Str;

/**
 * Lisibilité du site public pour les moteurs et les assistants IA :
 * données structurées schema.org, plan du site (sitemap.xml), llms.txt et versions Markdown.
 * Tout est construit depuis config/site.php et le catalogue publié : rien à saisir en double.
 */
class Seo
{
    /** Graphe JSON-LD commun à toutes les pages : la société, le showroom et le site. */
    public static function graph(): array
    {
        $c = config('site.contact');
        $legal = config('site.legal');
        $home = route('home');
        $profiles = array_values(array_filter((array) config('site.profiles')));
        $phones = array_column($c['phones'], 'tel');
        $address = [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Marché des Arts, Saint-Jean, Cocody',
            'addressLocality' => 'Abidjan',
            'addressRegion' => 'Abidjan',
            'addressCountry' => 'CI',
        ];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                array_filter([
                    '@type' => 'Organization',
                    '@id' => $home.'#organisation',
                    'name' => 'Maison du Store AM',
                    'legalName' => $legal['company'],
                    'url' => $home,
                    'logo' => asset('apple-touch-icon.png'),
                    'email' => $c['email'],
                    'telephone' => $phones[0],
                    'address' => $address,
                    'identifier' => [
                        ['@type' => 'PropertyValue', 'propertyID' => 'RCCM', 'value' => $legal['rccm']],
                        ['@type' => 'PropertyValue', 'propertyID' => 'NCC', 'value' => $legal['ncc']],
                    ],
                    'contactPoint' => array_map(fn ($tel) => ['@type' => 'ContactPoint', 'telephone' => $tel, 'contactType' => 'customer service', 'areaServed' => 'CI', 'availableLanguage' => 'fr'], $phones),
                    'sameAs' => $profiles ?: null,
                ]),
                array_filter([
                    '@type' => 'HomeGoodsStore',
                    '@id' => $home.'#showroom',
                    'name' => 'Maison du Store AM — showroom de Cocody',
                    'description' => 'Stores, rideaux et voilages sur mesure : prise de mesures à domicile, confection dans notre atelier d’Abidjan, livraison et pose par nos équipes.',
                    'url' => $home,
                    'image' => SiteImages::url('contact'),
                    'telephone' => $phones[0],
                    'email' => $c['email'],
                    'address' => $address,
                    'hasMap' => $c['maps'],
                    'areaServed' => ['@type' => 'City', 'name' => 'Abidjan'],
                    'currenciesAccepted' => 'XOF',
                    'parentOrganization' => ['@id' => $home.'#organisation'],
                    'sameAs' => $profiles ?: null,
                ]),
                [
                    '@type' => 'WebSite',
                    '@id' => $home.'#site',
                    'name' => 'Maison du Store AM',
                    'url' => $home,
                    'inLanguage' => 'fr-CI',
                    'publisher' => ['@id' => $home.'#organisation'],
                ],
            ],
        ];
    }

    /** Fil d'Ariane schema.org : [[libellé, url], …]. */
    public static function breadcrumb(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1]], $items, array_keys($items)),
        ];
    }

    public static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }

    /** Pages du plan du site : [url, date de dernière modification ou null]. */
    public static function sitemap(): array
    {
        $legalDate = self::legalDate();
        $products = Catalog::all();
        $latest = $products->max('updated');
        // Accueil et pages fixes : dernière modification d'un contenu publié (images, réalisations) ou du catalogue.
        $content = \App\Models\SiteContent::max('updated_at');
        $dates = array_filter([$content ? substr((string) $content, 0, 10) : null, $latest, $legalDate]);
        $site = $dates ? max($dates) : null;
        $urls = [[route('home'), $site], [route('quote'), $site], [route('site.info', 'a-propos'), $site], [route('site.info', 'contact'), $site]];
        if (Catalog::visible()) {
            $urls[] = [route('site.shop'), $latest];
            foreach (['rideaux', 'stores'] as $shop) $urls[] = [route('site.shop.show', $shop), $products->where('shop', $shop)->max('updated')];
            foreach ($products as $p) $urls[] = [route('site.product', $p->ref), $p->updated];
        }
        foreach (array_keys(SiteController::LEGAL) as $page) $urls[] = [route('site.legal', $page), $legalDate];

        return $urls;
    }

    /** Date de la version des pages légales (« 11 novembre 2026 » dans config/site.php). */
    private static function legalDate(): ?string
    {
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        if (! preg_match('/^(\d{1,2}) (\p{L}+) (\d{4})$/u', (string) config('site.legal.updated'), $m)) return null;
        $month = array_search(mb_strtolower($m[2]), $months, true);

        return $month === false ? null : sprintf('%04d-%02d-%02d', $m[3], $month + 1, $m[1]);
    }

    /** llms.txt : présentation de la maison et liens utiles, en Markdown (https://llmstxt.org). */
    public static function llms(): string
    {
        $c = config('site.contact');
        $phones = implode(', ', array_map(fn ($p) => '+225 '.$p['label'], $c['phones']));
        $lines = [
            '# Maison du Store AM',
            '',
            '> Stores, rideaux et voilages sur mesure à Abidjan (Côte d’Ivoire). Un conseiller prend les mesures à domicile, l’atelier confectionne, nos équipes livrent et posent. Showroom à Cocody, Saint-Jean, Marché des Arts.',
            '',
            '- Devis gratuit en ligne, rappel par un conseiller et prise de mesures à domicile.',
            '- Rendez-vous au showroom, à domicile ou par téléphone.',
            '- Zone d’intervention : Abidjan et environs ; hors Abidjan sur demande.',
            '- Électroménager et meubles : boutiques bientôt ouvertes.',
            '- Téléphone : '.$phones.' ; WhatsApp : '.$c['whatsapp'].' ; e-mail : '.$c['email'],
            '',
            '## Pages principales',
            '',
            '- [Accueil]('.route('home').') : boutiques, savoir-faire, réalisations, prise de rendez-vous',
            '- [Demander un devis]('.route('quote').') : stores ou rideaux, en quelques étapes',
            '- [À propos]('.route('site.info', 'a-propos').') : la maison, l’atelier et la méthode',
            '- [Contact]('.route('site.info', 'contact').') : adresse, téléphones, e-mail, WhatsApp',
        ];
        if (Catalog::visible()) {
            $lines[] = '- [Boutique Rideaux & voilages]('.route('site.shop.show', 'rideaux').')';
            $lines[] = '- [Boutique Stores]('.route('site.shop.show', 'stores').')';
            $products = Catalog::all();
            if ($products->isNotEmpty()) {
                $lines[] = '';
                $lines[] = '## Catalogue';
                $lines[] = '';
                foreach ($products as $p) {
                    $lines[] = '- ['.$p->name.']('.route('site.product', $p->ref).')'.($p->description ? ' : '.Str::limit(Str::squish($p->description), 140) : '');
                }
            }
        }
        $lines[] = '';
        $lines[] = '## Informations légales';
        $lines[] = '';
        foreach (SiteController::LEGAL as $page => $label) $lines[] = '- ['.$label.']('.route('site.legal', $page).')';
        $lines[] = '';
        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = '- [Plan du site]('.url('/sitemap.xml').')';

        return implode("\n", $lines)."\n";
    }

    /** Fiche produit en Markdown, pour un agent qui demande text/markdown. */
    public static function productMarkdown(object $p, array $shop): string
    {
        $lines = ['# '.$p->name, '', '> '.$shop['name'].' — Maison du Store AM, Abidjan. Sur mesure, pose incluse à Abidjan. Prix sur devis.', ''];
        if ($p->description) array_push($lines, $p->description, '');
        $facts = array_filter([
            'Référence' => $p->ref,
            'Type' => $p->type,
            'Disponibilité' => $p->available ? 'disponible' : 'sur commande',
            'Coloris' => implode(', ', array_column($p->colors, 0)),
            'Fonctions' => implode(', ', $p->features),
        ]);
        foreach ($facts as $k => $v) $lines[] = '- '.$k.' : '.$v;
        foreach ($p->specs as [$k, $v]) $lines[] = '- '.$k.' : '.$v;
        array_push($lines, '', '[Demander un devis pour ce modèle]('.route('quote', ['type' => $p->kind]).') · [Toute la boutique]('.route('site.shop.show', $p->shop).')');

        return implode("\n", $lines)."\n";
    }

    /** L'appelant préfère du Markdown (agent IA) : Accept text/markdown explicite, sans text/html devant. */
    public static function wantsMarkdown(\Illuminate\Http\Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept'));
        if (! str_contains($accept, 'text/markdown')) return false;
        $html = strpos($accept, 'text/html');

        return $html === false || strpos($accept, 'text/markdown') < $html;
    }

    public static function markdown(string $body)
    {
        return response($body, 200, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Vary' => 'Accept']);
    }
}
