<?php
namespace App\Http\Controllers;

use App\Models\{SiteVisit, SiteVisitEvent};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Str;

class AnalyticsController
{
    public function collect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visitor_id' => ['required','string','regex:/^[a-f0-9-]{36}$/i'],
            'session_id' => ['required','string','regex:/^[a-f0-9-]{36}$/i'],
            'event' => ['required','in:page_view,heartbeat,form_start,form_submit,quote_received,appointment_received,whatsapp_click,quote_click,appointment,product_view'],
            'path' => ['nullable','string','max:500'], 'title' => ['nullable','string','max:180'],
            'target' => ['nullable','string','max:120'], 'campaign' => ['nullable','string','max:190'],
            'referrer' => ['nullable','string','max:190'],
        ]);

        $visitorId = hash_hmac('sha256', Str::lower($data['visitor_id']), (string) config('app.key'));
        $sessionId = hash_hmac('sha256', Str::lower($data['session_id']), (string) config('app.key'));
        $path = preg_replace('/[?#].*$/', '', $data['path'] ?? '/') ?: '/';
        $visit = SiteVisit::firstOrNew(['session_key' => $sessionId]);
        $now = now();
        if (!$visit->exists) {
            // Hôte d'origine envoyé par le site (document.referrer) ; l'en-tête Referer ne donne que la page du site elle-même.
            $sent = strtolower(trim((string) ($data['referrer'] ?? '')));
            $referer = preg_match('/^[a-z0-9.-]+$/', $sent) ? $sent : (parse_url((string) $request->header('referer'), PHP_URL_HOST) ?: '');
            $source = strtolower($referer.' '.($data['campaign'] ?? ''));
            $origin = str_contains($source, 'whatsapp') ? 'whatsapp'
                : (str_contains($source, 'facebook') || str_contains($source, 'instagram') || str_contains($source, 'fbclid') ? 'facebook_instagram'
                : (str_contains($source, 'google') || str_contains($source, 'gclid') ? 'google'
                : ($referer !== '' && !str_contains($referer, $request->getHost()) && !str_ends_with($referer, 'maisondustoream.com') ? 'other_site' : 'direct')));
            $ua = (string) $request->userAgent();
            $visit->fill([
                'visitor_key' => $visitorId,
                'referrer_host' => $referer ? Str::limit($referer, 190, '') : null,
                'origin' => $origin,
                'campaign' => isset($data['campaign']) ? preg_replace('/[^A-Za-z0-9_.=,&-]/', '', $data['campaign']) : null,
                'device' => preg_match('/ipad|tablet/i', $ua) ? 'tablette' : (preg_match('/mobile|android|iphone/i', $ua) ? 'téléphone' : 'ordinateur'),
                'browser' => $this->browser($ua), 'operating_system' => $this->operatingSystem($ua),
                // No raw IP, IP hash, or exact location is stored. Cloudflare country is coarse and optional.
                'country_code' => preg_match('/^[A-Z]{2}$/', (string) $request->header('CF-IPCountry')) ? $request->header('CF-IPCountry') : null,
                'city' => $request->header('CF-IPCity') ? Str::limit(strip_tags(rawurldecode((string) $request->header('CF-IPCity'))), 100, '') : null,
                'arrived_at' => $now, 'page_views' => 0,
            ]);
        }
        $visit->current_path = $path;
        $visit->last_activity_at = $now;
        if ($data['event'] === 'page_view') $visit->page_views++;
        $visit->save();

        if (!in_array($data['event'], ['heartbeat'], true)) {
            SiteVisitEvent::create([
                'site_visit_id' => $visit->id, 'name' => $data['event'], 'path' => $path,
                'details' => array_filter(['title' => $data['title'] ?? null, 'target' => $data['target'] ?? null]), 'created_at' => $now,
            ]);
        }
        // Durée annoncée dans la politique de confidentialité : 13 mois. Purge au plus une fois par jour, sans tâche planifiée.
        if (\Illuminate\Support\Facades\Cache::add('analytics:purge', 1, $now->copy()->addDay())) {
            SiteVisit::where('last_activity_at', '<', $now->copy()->subMonths(13))->delete();
        }
        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }

    private function browser(string $ua): string
    {
        return match (true) { str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'Firefox/') => 'Firefox', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => 'Autre' };
    }
    private function operatingSystem(string $ua): string
    {
        return match (true) { str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Android') => 'Android', str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS', str_contains($ua, 'Mac OS') => 'macOS', str_contains($ua, 'Linux') => 'Linux', default => 'Autre' };
    }
}
