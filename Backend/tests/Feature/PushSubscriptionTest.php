<?php
namespace Tests\Feature;

use App\Models\{PushSubscription, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    // En production, l'abonnement échouait : user_id n'était pas enregistré (« Field 'user_id' doesn't have a default value »).
    public function test_subscription_is_saved_for_the_signed_in_user_and_moves_with_the_device(): void
    {
        $user = fn ($name) => User::create(['username' => $name, 'name' => $name, 'email' => $name.'@example.com', 'password' => 'TemporaryPass123!', 'role' => 'agent', 'must_change_password' => false]);
        [$awa, $ali] = [$user('awa'), $user('ali')];
        $payload = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'cle', 'auth' => 'secret'], 'user_id' => $ali->id];
        $this->actingAs($awa)->postJson('/admin/push/abonnement', $payload)->assertOk();
        $this->assertSame($awa->id, PushSubscription::sole()->user_id);
        $this->flushSession(); $this->actingAs($ali)->postJson('/admin/push/abonnement', $payload)->assertOk();
        $this->assertSame($ali->id, PushSubscription::sole()->user_id);
    }
}
