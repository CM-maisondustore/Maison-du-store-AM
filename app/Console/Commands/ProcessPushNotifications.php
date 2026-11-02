<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class ProcessPushNotifications extends Command
{
    protected $signature = 'push:process {--limit=100}';
    protected $description = 'Envoyer les alertes Push des comptes de l’espace professionnel';

    public function handle(): int
    {
        if (!class_exists(WebPush::class)) {
            $this->error('Dépendance minishlink/web-push absente : exécutez composer install après mise à jour des dépendances.');
            return self::FAILURE;
        }
        $keys = config('push');
        if (!$keys['public_key'] || !$keys['private_key']) {
            $this->error('Clés VAPID absentes : renseignez PUSH_VAPID_PUBLIC_KEY et PUSH_VAPID_PRIVATE_KEY.');
            return self::FAILURE;
        }

        $sender = new WebPush(['VAPID' => ['subject' => $keys['subject'], 'publicKey' => $keys['public_key'], 'privateKey' => $keys['private_key']]]);
        $limit = max(1, min(500, (int) $this->option('limit')));
        $items = DB::table('push_outbox')->whereNull('sent_at')->where('available_at', '<=', now())->orderBy('id')->limit($limit)->get();
        $processed = 0;
        foreach ($items as $item) {
            $locked = DB::table('push_outbox')->where('id', $item->id)->whereNull('sent_at')->where('available_at', '<=', now())
                ->update(['available_at' => now()->addMinutes(10), 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
            if (!$locked) continue;
            try {
                $notification = DB::table('notifications')->where('id', $item->notification_id)->first();
                $data = $notification ? json_decode($notification->data, true, flags: JSON_THROW_ON_ERROR) : null;
                $subscriptions = \App\Models\PushSubscription::where('user_id', $item->user_id)->get();
                if (!$data || $subscriptions->isEmpty()) {
                    DB::table('push_outbox')->where('id', $item->id)->update(['sent_at' => now(), 'last_error' => null, 'updated_at' => now()]);
                    continue;
                }
                foreach ($subscriptions as $stored) {
                    $sender->queueNotification(Subscription::create([
                        'endpoint' => $stored->endpoint, 'publicKey' => $stored->public_key,
                        'authToken' => $stored->auth_token, 'contentEncoding' => $stored->content_encoding,
                    ]), json_encode(['id' => $item->notification_id, ...$data], JSON_THROW_ON_ERROR), ['TTL' => 86400]);
                }
                $failed = [];
                foreach ($sender->flush() as $report) {
                    $endpoint = (string) $report->getRequest()->getUri();
                    $stored = $subscriptions->first(fn ($subscription) => hash_equals($subscription->endpoint, $endpoint));
                    if (!$stored) continue;
                    if ($report->isSuccess()) $stored->update(['last_used_at' => now()]);
                    elseif ($report->isSubscriptionExpired()) $stored->delete();
                    else $failed[] = $report->getReason();
                }
                if ($failed) throw new \RuntimeException(implode('; ', array_slice($failed, 0, 3)));
                DB::table('push_outbox')->where('id', $item->id)->update(['sent_at' => now(), 'last_error' => null, 'updated_at' => now()]);
                $processed++;
            } catch (\Throwable $exception) {
                $delayMinutes = min(1440, 2 ** min(10, $item->attempts + 1));
                DB::table('push_outbox')->where('id', $item->id)->update([
                    'available_at' => now()->addMinutes($delayMinutes),
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000), 'updated_at' => now(),
                ]);
                report($exception);
            }
        }
        $this->info("Alertes Push traitées : {$processed}.");
        return self::SUCCESS;
    }
}
