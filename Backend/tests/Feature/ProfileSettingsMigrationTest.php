<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_phone_and_theme_are_copied_to_the_v2_json_fields(): void
    {
        $id = DB::table('users')->insertGetId(['username' => 'awa', 'name' => 'Awa', 'email' => 'awa@example.com', 'password' => 'x', 'role' => 'agent', 'active' => true, 'must_change_password' => false, 'phone' => '+225 07 00 00 00', 'theme' => 'dark']);
        $kept = DB::table('users')->insertGetId(['username' => 'ali', 'name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'x', 'role' => 'manager', 'active' => true, 'must_change_password' => false, 'phone' => '+225 01', 'theme' => 'dark', 'profile' => json_encode(['phone' => '+225 05']), 'preferences' => json_encode(['theme' => 'auto'])]);

        (require database_path('migrations/2026_11_04_000001_copy_profile_settings_to_json.php'))->up();

        $user = User::find($id);
        $this->assertSame('+225 07 00 00 00', $user->profile['phone']);
        $this->assertSame('dark', $user->preferences['theme']);
        $this->assertSame('+225 07 00 00 00', $user->phone);
        // Une valeur déjà saisie dans l'Espace Pro v2 n'est jamais écrasée.
        $this->assertSame('+225 05', User::find($kept)->profile['phone']);
        $this->assertSame('auto', User::find($kept)->preferences['theme']);
    }
}
