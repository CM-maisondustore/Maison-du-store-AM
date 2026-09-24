<?php
namespace App\Http\Controllers\Staff;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
class UserController
{
    public function index() { return view('staff.users', ['users' => User::orderBy('name')->get()]); }
    public function store(Request $r)
    {
        $data = $r->validate(['username' => ['required', 'alpha_dash', 'max:80', 'unique:users'], 'name' => 'required|string|max:150', 'email' => 'required|email|max:200|unique:users', 'role' => 'required|in:admin,agent', 'password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
        $user = User::create($data); Audit::record('compte_cree', $user);
        return back()->with('success', 'Compte créé. Le mot de passe devra être changé à la première connexion.');
    }
    public function update(Request $r, User $user)
    {
        abort_if($user->id === $r->user()->id, 422, 'Votre propre compte ne peut pas être désactivé ici.');
        $data = $r->validate(['active' => 'required|boolean', 'password' => ['nullable', Password::min(12)->mixedCase()->numbers()]]);
        if (empty($data['password'])) unset($data['password']); else $data['must_change_password'] = true;
        $user->update($data); Audit::record('compte_modifie', $user);
        return back()->with('success', 'Compte mis à jour.');
    }
}
