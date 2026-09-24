<?php
// Called only through SSH. Generate a short-lived MySQL options file without
// putting credentials in a process argument or requiring proc_open in PHP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
try {
    require $argv[1].'/vendor/autoload.php';
    $app = require $argv[1].'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    umask(0077);
    $db = config('database.connections.mysql');
    $quote = fn ($value) => '"'.str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], (string) $value).'"';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $db['database'])) throw new RuntimeException();
    $content = "[client]\nhost=".$quote($db['host'])."\nport=".(int) $db['port']."\nuser=".$quote($db['username'])."\npassword=".$quote($db['password'])."\n";
    if (file_put_contents($argv[2], $content) === false || file_put_contents($argv[3], $db['database']) === false) throw new RuntimeException();
    chmod($argv[2], 0600); chmod($argv[3], 0600);
} catch (Throwable $e) {
    fwrite(STDERR, "Could not prepare MySQL backup. No sensitive details displayed.\n");
    exit(1);
}
