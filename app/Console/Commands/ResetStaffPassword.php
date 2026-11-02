<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
class ResetStaffPassword extends Command
{
    protected $signature = 'staff:password {username} {--password-file=}';
    protected $description = 'Réinitialiser un mot de passe interne depuis un accès serveur autorisé.';
    public function handle(): int
    {
        $user = User::where('username', $this->argument('username'))->first();
        if (!$user) { $this->error('Compte introuvable.'); return self::FAILURE; }
        $password = $this->option('password-file') ? trim(file_get_contents($this->option('password-file'))) : $this->secret('Nouveau mot de passe temporaire');
        if (Validator::make(['password' => $password], ['password' => ['required', Password::min(12)->mixedCase()->numbers()]])->fails()) { $this->error('Mot de passe insuffisant.'); return self::FAILURE; }
        $user->update(['password' => $password, 'must_change_password' => true]);
        $this->info('Mot de passe réinitialisé. Changement obligatoire à la connexion.'); return self::SUCCESS;
    }
}
