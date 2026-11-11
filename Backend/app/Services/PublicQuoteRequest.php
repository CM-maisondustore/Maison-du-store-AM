<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteOption;
use Illuminate\Support\Str;

/**
 * Demande de devis reçue du site public : lecture des choix du client pour l'espace pro.
 * - summary() : les choix dans l'ordre du parcours (type, finition ou modèle, tissus...).
 * - defaults() : les « choix communs » du formulaire de chiffrage, pré-remplis.
 * Les anciennes demandes n'ont que des libellés : elles sont retrouvées par libellé.
 */
class PublicQuoteRequest
{
    private const CONTROL = ['manuelle' => 'Manuelle', 'motorisee' => 'Motorisée', 'inconnu' => 'À définir avec le client'];

    private const ORDER_TYPE = ['installation' => 'Nouvelle installation', 'remplacement' => 'Remplacement', 'renovation' => 'Rénovation'];

    public static function isPublic(Quote $quote): bool
    {
        return ! empty($quote->product['model_id']) || ! empty($quote->product['finish_code']) || isset($quote->product['color']);
    }

    /** @return list<array{0: string, 1: string}> */
    public static function summary(Quote $quote): array
    {
        $p = $quote->product ?? [];
        $curtain = $quote->type === 'rideau';
        $rows = [['Type de projet', $curtain ? 'Rideaux et voilages' : 'Store']];
        if ($curtain) {
            // Parcours rideaux : le client choisit la finition, le modèle en découle.
            if (! empty($p['finish'])) {
                $rows[] = ['Finition choisie', $p['finish']];
            }
            if (! empty($p['model'])) {
                $rows[] = ['Modèle associé', $p['model']];
            }
            [$fabric, $sheer] = self::curtainLabels($p);
            $rows[] = ['Tissu de rideau', $fabric ?: 'Aucun'];
            $rows[] = ['Voilage', $sheer ?: 'Aucun'];
        } else {
            if (! empty($p['model'])) {
                $rows[] = ['Modèle choisi', $p['model']];
            }
            if (! empty($p['fabric'])) {
                $rows[] = ['Toile', $p['fabric']];
            }
            if (! empty($p['finish'])) {
                $rows[] = ['Finition', $p['finish']];
            }
            if (! empty($p['orderType'])) {
                $rows[] = ['Prestation', self::ORDER_TYPE[$p['orderType']] ?? $p['orderType']];
            }
        }
        if (! empty($p['control'])) {
            $rows[] = ['Manœuvre', self::CONTROL[$p['control']] ?? $p['control']];
        }
        if (! empty($p['color'])) {
            $rows[] = ['Coloris', config('site.colors.'.$p['color'].'.0', $p['color'])];
        }
        $windows = collect($quote->dimensions ?? [])->filter(fn ($w) => is_array($w) && isset($w['width'], $w['height']));
        if ($windows->isNotEmpty()) {
            $rows[] = ['Fenêtres', $windows->map(fn ($w) => trim(($w['label'] ?? '') ?: 'Fenêtre').' : '.self::cm($w['width']).' × '.self::cm($w['height']).' cm'.((int) ($w['quantity'] ?? 1) > 1 ? ' (×'.(int) $w['quantity'].')' : ''))->implode(' ; ')];
        }

        return $rows;
    }

    /** Choix communs du formulaire de chiffrage, déduits de la demande du client. */
    public static function defaults(Quote $quote): array
    {
        $p = $quote->product ?? [];
        $category = $quote->type === 'rideau' ? 'rideau' : 'store';
        $motorized = in_array($p['control'] ?? '', ['motorisee', 'Motorisée'], true);
        $base = ['category' => $category, 'motorized' => $motorized];
        if (! self::isPublic($quote)) {
            return $base;
        }

        $options = QuoteOption::where('active', true)->where('category', $category)->whereIn('kind', ['material', 'finish'])->get();
        $materials = $options->where('kind', 'material');
        if ($category === 'store') {
            $base['store_id'] = self::find($materials, $p['fabric_code'] ?? null, $p['fabric'] ?? null)?->id;
            $finish = self::find($options->where('kind', 'finish'), $p['finish_code'] ?? null, $p['finish'] ?? null);
        } else {
            [$fabric, $sheer] = self::curtainLabels($p);
            $base['curtain_id'] = self::find($materials->filter(fn ($o) => ($o->fabric_group ?: $o->category) === 'rideau'), $p['fabric_code'] ?? null, $fabric)?->id;
            $base['sheer_id'] = self::find($materials->where('fabric_group', 'voilage'), $p['sheer_code'] ?? null, $sheer)?->id;
            $finish = self::curtainFinish($options->where('kind', 'finish'), $p, $motorized);
        }
        if ($finish && $finish->compatible($category, 0, $motorized)) {
            $base['finish_id'] = $finish->id;
        }

        return array_filter($base, fn ($v) => $v !== null);
    }

    /** Libellés tissu / voilage, y compris pour les demandes où le tissu valait « Tissu + Voilage ». */
    private static function curtainLabels(array $p): array
    {
        $sheer = $p['sheer'] ?? null;
        $fabric = $p['fabric'] ?? null;
        if ($fabric && $sheer && Str::endsWith($fabric, ' + '.$sheer)) {
            $fabric = Str::beforeLast($fabric, ' + '.$sheer);
        } elseif ($fabric === $sheer) {
            $fabric = null;
        }

        return [$fabric ?: null, $sheer ?: null];
    }

    /**
     * Finition rideau du site (Wave, Ruflette, Barre de fer) vers la finition de chiffrage :
     * même code, ou code / libellé qui la contient, en préférant celle compatible avec la manœuvre.
     */
    private static function curtainFinish($finishes, array $p, bool $motorized): ?QuoteOption
    {
        $code = Str::lower((string) ($p['finish_code'] ?? ''));
        $label = Str::lower((string) ($p['finish'] ?? ''));
        if ($code === '' && $label === '') {
            return null;
        }
        $matches = $finishes->filter(fn ($o) => ($code !== '' && (Str::lower($o->code) === $code || Str::startsWith(Str::lower($o->code), $code.'-')))
            || ($label !== '' && Str::contains(Str::lower($o->label), $label)));

        // Priorité : compatible avec la manœuvre, puis grille tarifaire actuelle (choix public), puis code identique.
        return $matches->sortByDesc(fn ($o) => [(int) $o->compatible('rideau', 0, $motorized), (int) $o->public_choice, (int) (Str::lower($o->code) === $code)])->first();
    }

    private static function find($options, ?string $code, ?string $label): ?QuoteOption
    {
        return ($code ? $options->firstWhere('code', $code) : null)
            ?? ($label ? $options->first(fn ($o) => Str::lower($o->label) === Str::lower($label)) : null);
    }

    private static function cm($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 1, ',', ' '), '0'), ',');
    }
}
