<?php
// Called by rollback.sh before activating a release that cannot read private asset references.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (Illuminate\Support\Facades\Schema::hasTable('document_template_revisions')) App\Services\DocumentLayout::prepareRollback();
echo "PDF settings prepared for the previous renderer; revisions, files and archives retained.\n";
