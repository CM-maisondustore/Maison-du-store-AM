<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffPushNotifications
{
    /** @param iterable<User> $users */
    public function send(iterable $users, string $title, string $message, string $url): void
    {
        foreach ($users as $user) {
            try {
                DB::transaction(function () use ($user, $title, $message, $url) {
                    $id = (string) Str::uuid();
                    $now = now();
                    DB::table('notifications')->insert([
                        'id' => $id,
                        'type' => \App\Notifications\StaffAlert::class,
                        'notifiable_type' => $user->getMorphClass(),
                        'notifiable_id' => $user->id,
                        'data' => json_encode(['title' => $title, 'message' => $message, 'url' => $url], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('push_outbox')->insertOrIgnore([
                        'notification_id' => $id,
                        'user_id' => $user->id,
                        'available_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function admins(string $title, string $message, string $url): void
    {
        $this->send(User::whereIn('role', ['admin', 'manager'])->where('active', true)->get(), $title, $message, $url);
    }
}
