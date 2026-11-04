<?php
namespace App\Http\Controllers\Staff;

use App\Models\MediaAsset;
use App\Services\{Audit, MediaStorage, StaffInterface};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\Rule;

class ProfileController
{
    /** Savoir-faire et jours proposés à l'agent en cases à cocher. */
    public const SKILLS = ['stores' => 'Stores', 'rideaux' => 'Rideaux et voilages', 'motorisation' => 'Motorisation', 'electromenager' => 'Installation électroménager', 'meubles' => 'Montage de meubles', 'sav' => 'Service après-vente'];
    public const DAYS = ['lun' => 'Lundi', 'mar' => 'Mardi', 'mer' => 'Mercredi', 'jeu' => 'Jeudi', 'ven' => 'Vendredi', 'sam' => 'Samedi', 'dim' => 'Dimanche'];

    /** Communes proposées en Oui / Non dans le profil de l'agent, dans l'ordre de l'étape 1 du devis. */
    public static function zones(): array { return [...StaffInterface::COMMUNES, StaffInterface::OUTSIDE_ABIDJAN]; }

    public function profile() { return view('staff.profile'); }
    public function updateProfile(Request $r)
    {
        $rules = ['name' => 'required|string|max:150', 'civility' => 'nullable|in:M.,Mme', 'title' => 'nullable|string|max:100', 'phone' => 'nullable|string|max:30', 'whatsapp' => 'nullable|boolean',
            'availability' => 'nullable|in:available,away', 'cover' => MediaStorage::rules()['image'], 'remove_cover' => 'nullable|boolean'];
        $rules += $r->user()->isAdmin()
            ? ['signature' => 'nullable|string|max:500', 'back_on' => 'nullable|date_format:Y-m-d']
            : ['skills' => 'nullable|string|max:300', 'hours' => 'nullable|string|max:150', 'emergency_name' => 'nullable|string|max:150', 'emergency_phone' => 'nullable|string|max:30',
                'skill_list' => 'nullable|array', 'skill_list.*' => [Rule::in(array_keys(self::SKILLS))], 'days' => 'nullable|array', 'days.*' => [Rule::in(array_keys(self::DAYS))],
                'zone_list' => 'nullable|array:'.implode(',', self::zones()), 'zone_list.*' => 'in:0,1'];
        $data = $r->validate($rules);
        $name = $data['name']; unset($data['name'], $data['cover'], $data['remove_cover']);
        $profile = [...($r->user()->profile ?? []), ...$data, 'whatsapp' => $r->boolean('whatsapp')];
        // Cases décochées : le navigateur n'envoie rien, la liste devient vide. Communes : seules les « Oui » sont gardées.
        if (!$r->user()->isAdmin()) $profile = [...$profile, 'skill_list' => array_values($data['skill_list'] ?? []), 'days' => array_values($data['days'] ?? []),
            'zone_list' => array_values(array_intersect(self::zones(), array_keys(array_filter($data['zone_list'] ?? [], fn ($v) => $v === '1'))))];
        $old = $profile['cover_media_id'] ?? null;
        DB::transaction(function () use ($r, $name, $profile, $old) {
            if ($r->hasFile('cover')) $profile['cover_media_id'] = MediaStorage::fromBytes($r->file('cover')->get())->id;
            elseif ($r->boolean('remove_cover')) unset($profile['cover_media_id']);
            $r->user()->update(['name' => $name, 'profile' => $profile]);
            if ($old && $old !== ($profile['cover_media_id'] ?? null)) MediaAsset::whereKey($old)->delete();
        });
        Audit::record('profil_modifie', $r->user());
        return back()->with('success', 'Profil enregistré.');
    }
    /** Photo de couverture : chacun ne lit que la sienne. */
    public function cover(Request $r)
    {
        $id = $r->user()->profile['cover_media_id'] ?? null;
        abort_unless($id, 404);
        $asset = MediaAsset::findOrFail($id);
        return response(base64_decode($asset->payload), 200, ['Content-Type' => $asset->mime, 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'"]);
    }
    public function settings() { return view('staff.settings'); }
    public function updateSettings(Request $r)
    {
        $data = $r->validate(['theme' => 'required|in:light,dark,auto', 'reduce_motion' => 'nullable|boolean']);
        $r->user()->update(['preferences' => [...($r->user()->preferences ?? []), 'theme' => $data['theme'], 'reduce_motion' => $r->boolean('reduce_motion')]]);
        return back()->with('success', 'Paramètres enregistrés.');
    }
    public function logoutOthers(Request $r)
    {
        $r->validate(['password' => 'required|current_password']);
        Auth::logoutOtherDevices($r->input('password'));
        $r->session()->put('password_hash_web', $r->user()->fresh()->password);
        Audit::record('deconnexion_autres_appareils', $r->user());
        return back()->with('success', 'Les autres appareils sont déconnectés.');
    }
}
