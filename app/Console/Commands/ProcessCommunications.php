<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class ProcessCommunications extends Command
{
    protected $signature='communications:process {--limit=100}';
    protected $description='Préparer les brouillons et traiter les envois explicitement automatisés';
    public function handle(\App\Services\CommunicationAutomation $automation): int
    {
        try {
            $result=$automation->process(max(1,min(500,(int)$this->option('limit'))));
            $this->info(json_encode($result)); return self::SUCCESS;
        } catch (\Throwable $e) {
            \App\Models\CommunicationCheck::updateOrCreate(['key'=>'scheduler'],['successful'=>false,'checked_at'=>now()]);
            $this->error('Le traitement des communications a échoué. Vérifiez la configuration et les règles.');
            return self::FAILURE;
        }
    }
}
