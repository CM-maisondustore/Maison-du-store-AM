<?php
namespace App\Console\Commands;
use App\Services\MdsTemplates;
use Illuminate\Console\Command;

class ApplyMdsTemplates extends Command
{
    protected $signature='mds:templates {--force : Appliquer sans confirmation}';
    protected $description='Republie les modèles PDF et les messages préremplis Maison du Store AM (les versions PDF précédentes restent restaurables).';
    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Remplacer la mise en page et les messages préremplis actuels ?')) return self::FAILURE;
        $this->info(MdsTemplates::apply().' modèle(s) mis à jour.');
        return self::SUCCESS;
    }
}
