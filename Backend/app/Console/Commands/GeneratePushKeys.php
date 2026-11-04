<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GeneratePushKeys extends Command
{
    protected $signature = 'push:keys';
    protected $description = 'Générer une paire de clés VAPID pour Web Push';

    public function handle(): int
    {
        if (!class_exists(VAPID::class)) {
            $this->error('Dépendance minishlink/web-push absente. Installez les dépendances Composer d’abord.');
            return self::FAILURE;
        }
        $keys = VAPID::createVapidKeys();
        $this->line('PUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('PUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->warn('Ajoutez les deux valeurs aux variables d’environnement du serveur. Ne partagez ni ne commitez la clé privée. Conservez cette paire pour toutes les souscriptions existantes.');
        return self::SUCCESS;
    }
}
