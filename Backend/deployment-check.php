<?php

// CLI-only release checks. This file is outside public/ and never prints secrets.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$failures = [];
if (!in_array(config('app.env'), ['production', 'staging'], true)) $failures[] = 'APP_ENV must be production or staging.';
if (config('app.debug')) $failures[] = 'APP_DEBUG must be false.';
if (!str_starts_with(config('app.url'), 'https://')) $failures[] = 'APP_URL must use HTTPS.';
if (!config('session.secure')) $failures[] = 'SESSION_SECURE_COOKIE must be true.';
if (!config('app.key')) $failures[] = 'APP_KEY is missing.';
try { $app->make('encrypter'); } catch (Throwable $exception) { $failures[] = 'APP_KEY is invalid.'; }
if (config('database.default') !== 'mysql') $failures[] = 'Hostinger deployment expects MySQL.';
if (!is_writable(storage_path('framework/sessions'))) $failures[] = 'Storage is not writable.';
if (in_array('--database', $argv, true)) {
    try {
        foreach (['products', 'quotes', 'appointments'] as $table) {
            Illuminate\Support\Facades\DB::table($table)->limit(1)->get();
        }
    } catch (Throwable $exception) {
        $failures[] = 'Database or expected tables unavailable.';
    }
}
foreach ($failures as $message) fwrite(STDERR, $message.PHP_EOL);
if ($failures) exit(1);
echo 'Deployment checks passed.'.PHP_EOL;
