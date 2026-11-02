<?php
namespace App\Http\Controllers\Staff;

use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController
{
    public function profile() { return view('staff.profile'); }
    public function updateProfile(Request $r)
    {
        $rules = ['name' => 'required|string|max:150', 'civility' => 'nullable|in:M.,Mme', 'title' => 'nullable|string|max:100', 'phone' => 'nullable|string|max:30', 'whatsapp' => 'nullable|boolean'];
        $rules += $r->user()->isAdmin()
            ? ['signature' => 'nullable|string|max:500', 'availability' => 'nullable|in:available,away', 'back_on' => 'nullable|date_format:Y-m-d']
            : ['zones' => 'nullable|string|max:300', 'skills' => 'nullable|string|max:300', 'hours' => 'nullable|string|max:150', 'emergency_name' => 'nullable|string|max:150', 'emergency_phone' => 'nullable|string|max:30'];
        $data = $r->validate($rules);
        $name = $data['name']; unset($data['name']);
        $r->user()->update(['name' => $name, 'profile' => [...($r->user()->profile ?? []), ...$data, 'whatsapp' => $r->boolean('whatsapp')]]);
        Audit::record('profil_modifie', $r->user());
        return back()->with('success', 'Profil enregistré.');
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
