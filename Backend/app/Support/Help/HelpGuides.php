<?php

namespace App\Support\Help;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Guides d'utilisation de l'espace pro : un fichier Markdown par guide dans resources/help.
 *
 * En-tête de chaque fichier (entre deux lignes « --- ») :
 *   title, summary, group, order, roles (agent, manager, admin), routes (noms de routes, « * » accepté).
 * Une image « ![texte](capture:nom) » affiche public/help/nom.webp si la capture existe ; sinon elle est omise.
 */
class HelpGuides
{
    public const ROLES = ['agent' => 'Agent', 'manager' => 'Manager', 'admin' => 'Admin principal'];
    /** Ordre d'affichage des rubriques sur la page Aide. */
    public const GROUPS = ['Prise en main', 'Sur le terrain', 'Devis et ventes', 'Planning', 'Clients et messages', 'Catalogue', 'Documents et site', 'Administration'];

    private static ?array $all = null;

    public static function directory(): string
    {
        return resource_path('help');
    }

    /** @return array<string,array> tous les guides, indexés par identifiant, triés par rubrique puis ordre. */
    public static function all(): array
    {
        if (self::$all !== null) return self::$all;
        $guides = [];
        foreach (glob(self::directory().'/*.md') ?: [] as $file) {
            $guide = self::parse(basename($file, '.md'), (string) file_get_contents($file));
            $guides[$guide['slug']] = $guide;
        }
        uasort($guides, fn ($a, $b) => [self::groupRank($a['group']), $a['order'], $a['title']] <=> [self::groupRank($b['group']), $b['order'], $b['title']]);
        return self::$all = $guides;
    }

    public static function flush(): void
    {
        self::$all = null;
    }

    public static function parse(string $slug, string $text): array
    {
        $meta = [];
        $body = $text;
        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $text, $m)) {
            foreach (preg_split('/\R/', $m[1]) as $line) {
                if (preg_match('/^([a-z_]+)\s*:\s*(.*)$/', trim($line), $kv)) $meta[$kv[1]] = trim($kv[2]);
            }
            $body = $m[2];
        }
        $list = fn (string $key) => array_values(array_filter(array_map('trim', explode(',', $meta[$key] ?? ''))));
        return [
            'slug' => $slug,
            'title' => $meta['title'] ?? Str::headline($slug),
            'summary' => $meta['summary'] ?? '',
            'group' => $meta['group'] ?? 'Prise en main',
            'order' => (int) ($meta['order'] ?? 100),
            'roles' => array_values(array_intersect($list('roles'), array_keys(self::ROLES))),
            'routes' => $list('routes'),
            'body' => trim($body),
        ];
    }

    private static function groupRank(string $group): int
    {
        $rank = array_search($group, self::GROUPS, true);
        return $rank === false ? count(self::GROUPS) : $rank;
    }

    /** Rôle de l'utilisateur pour l'aide : agent, manager ou admin (admin principal). */
    public static function role(User $user): string
    {
        return $user->isOwner() ? 'admin' : ($user->isAdmin() ? 'manager' : 'agent');
    }

    /** @return array<string,array> guides visibles pour cet utilisateur. */
    public static function forUser(User $user): array
    {
        $role = self::role($user);
        return array_filter(self::all(), fn ($g) => in_array($role, $g['roles'], true));
    }

    public static function find(User $user, string $slug): ?array
    {
        return self::forUser($user)[$slug] ?? null;
    }

    /** Guide de la page affichée (bouton « ? »), s'il en existe un pour ce rôle. */
    public static function forRoute(User $user, ?string $routeName): ?array
    {
        if (!$routeName) return null;
        foreach (self::forUser($user) as $guide) {
            foreach ($guide['routes'] as $pattern) if (Str::is($pattern, $routeName)) return $guide;
        }
        return null;
    }

    /** Guide suivant dans l'ordre de lecture, pour ce rôle. */
    public static function next(User $user, string $slug): ?array
    {
        $keys = array_keys(self::forUser($user));
        $i = array_search($slug, $keys, true);
        return $i === false || !isset($keys[$i + 1]) ? null : self::forUser($user)[$keys[$i + 1]];
    }

    /** @return array<string,array<int,array>> guides visibles regroupés par rubrique. */
    public static function grouped(User $user): array
    {
        $groups = [];
        foreach (self::forUser($user) as $guide) $groups[$guide['group']][] = $guide;
        return $groups;
    }

    public static function capturePath(string $name): string
    {
        return public_path('help/'.$name.'.webp');
    }

    /** Markdown → HTML. Le HTML brut saisi dans un guide est retiré ; les liens sont limités à http(s), mailto et chemins internes. */
    public static function html(array $guide): string
    {
        $body = preg_replace_callback('/!\[([^\]]*)\]\(capture:([a-z0-9-]+)\)/', function ($m) {
            $file = self::capturePath($m[2]);
            return is_file($file) ? '![' . $m[1] . '](' . asset('help/'.$m[2].'.webp') . '?v=' . filemtime($file) . ')' : '';
        }, $guide['body']);
        // Liens entre guides : (guide:identifiant) → page du guide.
        $body = preg_replace_callback('/\]\(guide:([a-z0-9-]+)(#[a-z0-9-]+)?\)/', fn ($m) => '](' . route('staff.help.show', $m[1]) . ($m[2] ?? '') . ')', $body);
        $html = Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        // Images : chargement différé et dimensions fluides ; encadrés « Bon à savoir ».
        $html = str_replace('<img ', '<img loading="lazy" decoding="async" ', $html);
        // Captures de téléphone : affichées à la taille d'un écran de téléphone.
        foreach (self::phoneCaptures() as $name) $html = str_replace('<img loading="lazy" decoding="async" src="'.asset('help/'.$name.'.webp'), '<img class="help-shot-phone" loading="lazy" decoding="async" src="'.asset('help/'.$name.'.webp'), $html);
        return preg_replace('#<blockquote>\s*<p><strong>([^<]+)</strong>#u', '<blockquote class="help-note"><p><strong>$1</strong>', $html);
    }

    /** @return string[] noms des captures prises sur un écran de téléphone (resources/help/captures.json). */
    public static function phoneCaptures(): array
    {
        $file = self::directory().'/captures.json';
        if (!is_file($file)) return [];
        $captures = json_decode((string) file_get_contents($file), true)['captures'] ?? [];
        return array_column(array_filter($captures, fn ($c) => !empty($c['mobile'])), 'name');
    }

    /** Texte brut pour la recherche sur la page Aide. */
    public static function plain(array $guide): string
    {
        $text = preg_replace(['/!\[[^\]]*\]\([^)]*\)/', '/\]\([^)]*\)/', '/[#*_>`\[\]]/'], ['', ']', ' '], $guide['body']);
        return Str::lower(Str::ascii($guide['title'].' '.$guide['summary'].' '.preg_replace('/\s+/', ' ', $text)));
    }
}
