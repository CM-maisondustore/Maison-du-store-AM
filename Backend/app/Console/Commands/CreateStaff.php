<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
class CreateStaff extends Command
{
    protected $signature = 'staff:create {username} {email} {--admin} {--password-file=}';
    protected $description = 'Créer un compte interne avec un mot de passe saisi sans affichage ou lu dans un fichier privé.';
    public function handle(): int
    {
        $password = $this->option('password-file') ? trim(file_get_contents($this->option('password-file'))) : $this->secret('Mot de passe temporaire (12 caractères minimum, majuscules, minuscules et chiffres)');
        $data = ['username' => $this->argument('username'), 'email' => $this->argument('email'), 'password' => $password];
        $validator = Validator::make($data, ['username' => 'required|alpha_dash|max:80|unique:users', 'email' => 'required|email|max:200|unique:users', 'password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
        if ($validator->fails()) { $this->error('Identifiant ou e-mail déjà utilisé, ou données invalides. Aucun compte créé.'); return self::FAILURE; }
        User::create([...$data, 'name' => $data['username'], 'role' => $this->option('admin') ? 'admin' : 'agent']);
        $this->info('Compte créé. Changement de mot de passe obligatoire à la première connexion.'); return self::SUCCESS;
    }
}
