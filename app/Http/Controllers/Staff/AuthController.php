<?php
namespace App\Http\Controllers\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, RateLimiter};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use App\Services\Audit;
class AuthController
{
    public function form() { return response()->view('staff.login')->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex'); }
    public function login(Request $r)
    {
        $data = $r->validate(['username' => 'required|string|max:100', 'password' => 'required|string|max:200']);
        $key = 'staff-login:'.hash('sha256', mb_strtolower($data['username']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) throw ValidationException::withMessages(['username' => 'Trop de tentatives. Réessayez dans une minute.']);
        if (!Auth::attempt([...$data, 'active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['username' => 'Identifiants incorrects.']);
        }
        RateLimiter::clear($key); $r->session()->regenerate(); Audit::record('connexion', $r->user());
        return redirect()->intended(route('staff.dashboard'));
    }
    public function logout(Request $r)
    {
        Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken();
        return redirect()->route('login');
    }
    public function password() { return view('staff.password'); }
    public function changePassword(Request $r)
    {
        $data = $r->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->mixedCase()->numbers()]]);
        $r->user()->update(['password' => $data['password'], 'must_change_password' => false]);
        $r->session()->regenerate(); $r->session()->put('password_hash_web', $r->user()->password);
        Audit::record('mot_de_passe_modifie', $r->user());
        return redirect()->route('staff.dashboard')->with('success', 'Mot de passe enregistré.');
    }
}
