<?php
namespace App\Http\Controllers\Staff;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\{Rule, ValidationException};
class UserController
{
    public function index() { return view('staff.users', ['users' => User::orderBy('name')->get()]); }
    public function store(Request $r)
    {
        $data = $r->validate(['username' => ['required', 'alpha_dash', 'max:80', 'unique:users'], 'name' => 'required|string|max:150', 'email' => 'required|email|max:200|unique:users', 'role' => ['required', Rule::in(array_keys(User::ROLES))], 'password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
        $user = User::create($data); Audit::record('compte_cree', $user);
        return back()->with('success', 'Compte créé. Le mot de passe devra être changé à la première connexion.');
    }
    public function update(Request $r, User $user)
    {
        abort_if($user->id === $r->user()->id, 422, 'Votre propre compte ne peut pas être désactivé ici.');
        $data = $r->validate(['active' => 'required|boolean', 'password' => ['nullable', Password::min(12)->mixedCase()->numbers()],
            'role' => ['nullable', Rule::in(array_keys(User::ROLES))], 'shops' => 'nullable|array', 'shops.*' => [Rule::in(array_keys(User::SHOPS))]]);
        if (empty($data['password'])) unset($data['password']); else $data['must_change_password'] = true;
        if (empty($data['role'])) unset($data['role']);
        // Boutiques de l'agent : il ne voit que celles cochées par l'admin, au moins une.
        if ($r->has('shops_sent') && ($data['role'] ?? $user->role) === 'agent' && empty($data['shops'])) throw ValidationException::withMessages(['shops' => 'Cochez au moins une boutique pour cet agent.']);
        if ($r->has('shops_sent')) $data['profile'] = [...($user->profile ?? []), 'shops' => array_values(array_unique($data['shops'] ?? []))];
        unset($data['shops']);
        $user->update($data); Audit::record('compte_modifie', $user);
        return back()->with('success', 'Compte mis à jour.');
    }
}
