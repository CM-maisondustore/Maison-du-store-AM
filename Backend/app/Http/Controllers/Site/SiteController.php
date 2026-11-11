<?php

namespace App\Http\Controllers\Site;

use App\Models\SiteContent;
use App\Services\AppointmentSchedule;
use App\Services\PublicQuoteChoices;
use App\Services\QuotePhone;
use App\Support\Site\Catalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Pages publiques de la refonte V2 (Stores & Rideaux).
 * Les formulaires envoient leurs demandes aux API existantes de l'espace pro :
 * /api/quotes/stores, /api/quotes/curtains et /api/appointments.
 */
class SiteController
{
    public function home(AppointmentSchedule $schedule)
    {
        $contents = SiteContent::with('media')->where('published', true)->orderBy('position')->orderBy('id')->get();
        $fallback = config('site.realisations');
        $portfolio = $contents->where('kind', 'portfolio')->values()->map(fn (SiteContent $c, int $i) => [
            't' => $c->title,
            's' => $c->description,
            'img' => $c->media?->url() ?? (isset($fallback[$i]) ? asset($fallback[$i]['img']) : null),
            'alt' => $c->image_alt ?: $c->title,
        ]);
        if ($portfolio->isEmpty()) {
            $portfolio = collect($fallback)->map(fn ($r) => ['t' => $r['t'], 's' => $r['s'], 'img' => asset($r['img']), 'alt' => $r['t']]);
        }

        return view('site.landing', [
            'hero' => $contents->firstWhere('slot', 'hero'),
            'portfolio' => $portfolio,
            'bubblePool' => $this->bubblePool(),
            'dates' => $this->appointmentDates($schedule),
            'slots' => config('appointments.slots'),
        ]);
    }

    public function shop(Request $request, ?string $shop = null)
    {
        abort_unless(Catalog::visible(), 404);
        $m = $shop ? config('site.shops.'.$shop) : null;
        $base = Catalog::forShop($shop);

        $active = collect($request->input('f', []))
            ->filter(fn ($v, $k) => in_array($k, Catalog::GROUPS, true) && is_array($v))
            ->map(fn ($v) => array_values(array_filter($v, 'is_string')))
            ->filter()
            ->all();
        $sort = array_key_exists((string) $request->query('sort'), Catalog::SORTS) ? $request->query('sort') : 'pert';
        $q = mb_substr(trim(is_string($request->query('q')) ? $request->query('q') : ''), 0, 80);

        $items = Catalog::sort($base->filter(fn ($p) => Catalog::matches($p, $active, $q))->values(), $sort);
        $shown = max(Catalog::PER_PAGE, min((int) $request->query('shown', Catalog::PER_PAGE), $items->count() + Catalog::PER_PAGE));
        $types = $base->pluck('type')->filter()->unique()->values();

        $data = [
            'shop' => $shop,
            'm' => $m,
            'filter' => (object) ['q' => $q, 'sort' => $sort, 'active' => $active],
            'chips' => collect($active)->flatMap(fn ($vals, $g) => collect($vals)->map(fn ($v) => ['group' => $g, 'label' => $v]))->values()->all(),
            'filterGroups' => Catalog::groups($base, $active, $q),
            'items' => $items->take($shown),
            'total' => $items->count(),
            'shown' => $shown,
            'typeChips' => $shop ? $types->map(fn ($t) => ['label' => $t, 'count' => $base->where('type', $t)->count()]) : collect(),
            'baseCount' => $base->count(),
            'counts' => Catalog::all()->countBy('shop'),
        ];

        if ($request->header('X-Partial')) {
            return response()->json([
                'results' => view('site.shop._results', $data)->render(),
                'filters' => view('site.shop._filters', $data)->render(),
                'chips' => view('site.shop._type_chips', $data)->render(),
                'total' => $data['total'],
            ]);
        }

        return view('site.shop.index', $data);
    }

    public function product(string $reference)
    {
        $p = Catalog::find($reference);
        abort_unless($p, 404);
        $similar = Catalog::forShop($p->shop)->where('ref', '!=', $p->ref)
            ->sortByDesc(fn ($s) => $s->type === $p->type)->take(4);

        return view('site.shop.product', ['p' => $p, 'm' => config('site.shops.'.$p->shop), 'similar' => $similar]);
    }

    public function devis(Request $request)
    {
        $type = in_array($request->query('type'), ['store', 'rideau'], true) ? $request->query('type') : null;
        $models = PublicQuoteChoices::models();
        $model = collect($models)->firstWhere('id', (string) $request->query('modele'));
        if ($model && ! $type) {
            $type = $model['category'];
        }

        return view('site.devis', [
            'type' => $type ?? 'store',
            'preset' => $model['id'] ?? null,
            'choices' => [
                'models' => $models,
                'options' => PublicQuoteChoices::data(),
                'finishes' => PublicQuoteChoices::finishes(),
                'messages' => PublicQuoteChoices::messages(),
                'colors' => collect(config('site.colors'))->map(fn ($c, $k) => ['id' => $k, 'label' => $c[0], 'hex' => $c[1]])->values(),
                'prefixes' => QuotePhone::PREFIXES,
            ],
            'endpoints' => ['store' => route('quotes.stores'), 'rideau' => route('quotes.curtains')],
            // Cartes de départ (image, titre, texte) modifiables dans l'espace pro.
            'cards' => SiteContent::with('media')->where('kind', 'quote')->where('published', true)->get()->keyBy('slot'),
        ]);
    }

    public function legal(string $page)
    {
        $titles = ['mentions-legales' => 'Mentions légales', 'cgv' => 'Conditions générales', 'confidentialite' => 'Politique de confidentialité', 'cookies' => 'Préférences cookies'];

        return view('site.legal', ['page' => $page, 'title' => $titles[$page]]);
    }

    /** Réserve de produits pour les bulles aléatoires de l'accueil. */
    private function bubblePool(): array
    {
        return Catalog::all()->filter(fn ($p) => $p->available)->shuffle()->take(40)->map(fn ($p) => [
            'name' => $p->name,
            'shop' => $p->shop,
            'label' => config('site.shops.'.$p->shop.'.name'),
            'img' => $p->image,
            'href' => route('site.product', $p->ref),
        ])->values()->all();
    }

    /** Les six prochains jours ouverts selon les réglages de rendez-vous de l'espace pro. */
    private function appointmentDates(AppointmentSchedule $schedule): array
    {
        $dates = [];
        $d = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        for ($i = 0; $i < 30 && count($dates) < 6; $i++, $d = $d->addDay()) {
            $open = collect(config('appointments.slots'))->contains(fn ($t) => $schedule->isOpen($d->format('Y-m-d'), $t));
            if ($open) {
                $dates[] = ['value' => $d->format('Y-m-d'), 'd' => $d->locale('fr')->translatedFormat('D'), 'n' => $d->format('j'), 'long' => $d->locale('fr')->translatedFormat('D j F')];
            }
        }

        return $dates;
    }
}
