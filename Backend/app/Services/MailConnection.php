<?php
namespace App\Services;
use App\Models\CommunicationCheck;
use Illuminate\Support\Facades\Mail;
class MailConnection
{
    public function fingerprint(): string
    {
        return hash_hmac('sha256',json_encode(config('mail.mailers.smtp')),config('app.key'));
    }
    public function verified(): bool
    {
        return app(CommercialMail::class)->configured() && CommunicationCheck::where('key','smtp')->where('successful',true)->where('fingerprint',$this->fingerprint())->exists();
    }
    public function check(): bool
    {
        $ok=false; $transport=null;
        try {
            if (!app(CommercialMail::class)->configured()) throw new \RuntimeException('Not configured');
            Mail::purge('smtp'); $transport=Mail::mailer('smtp')->getSymfonyTransport();
            $transport->start(); $ok=true;
        } catch (\Throwable $e) { $ok=false; }
        finally { if ($transport) try { $transport->stop(); } catch (\Throwable $e) {} }
        CommunicationCheck::updateOrCreate(['key'=>'smtp'],['successful'=>$ok,'checked_at'=>now(),'fingerprint'=>$this->fingerprint()]);
        return $ok;
    }
}
