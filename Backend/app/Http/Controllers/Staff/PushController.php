<?php

namespace App\Http\Controllers\Staff;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PushController
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->paginate(30);
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return view('staff.notifications', compact('notifications'));
    }

    public function publicKey()
    {
        abort_unless(config('push.public_key'), 503, 'Les notifications Push ne sont pas configurées.');
        return response()->json(['publicKey' => config('push.public_key')]);
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'starts_with:https://', 'max:4096', function ($attribute, $value, $fail) {
                $host = strtolower((string) parse_url($value, PHP_URL_HOST));
                $allowed = ['fcm.googleapis.com'];
                foreach (['push.services.mozilla.com', 'push.apple.com', 'notify.windows.com', 'wns.windows.com'] as $suffix) {
                    if ($host === $suffix || str_ends_with($host, '.'.$suffix)) return;
                }
                if (!in_array($host, $allowed, true)) $fail('Le fournisseur de notifications du navigateur n’est pas reconnu.');
            }],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);
        $hash = hash('sha256', $data['endpoint']);
        PushSubscription::updateOrCreate(['endpoint_hash' => $hash], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'label' => $data['label'] ?? null,
        ]);
        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'starts_with:https://', 'max:4096']]);
        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();
        return response()->json(['ok' => true]);
    }

    public function markRead(Request $request, string $notification)
    {
        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }
}
