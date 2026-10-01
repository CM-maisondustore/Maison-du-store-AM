<?php
// CLI-only synthetic visual fixtures. No database or production client data is read.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$destination=dirname(__DIR__,2).'/artifacts/document-layout';
if (!is_dir($destination)) mkdir($destination,0700,true);
$presentation=[...App\Services\DocumentLayout::defaults(),'issuer_name'=>'Maison du Store AM','issuer_address'=>'Abidjan','issuer_registration'=>'Exemple RCCM','issuer_tax_note'=>'Exemple de mention fiscale'];
foreach (['quote'=>['single','four','thirty','store','discount','negotiated','individual'],'invoice'=>['four'],'receipt'=>['four']] as $type=>$scenarios) foreach ($scenarios as $scenario) {
    $data=App\Services\DocumentPreview::data($type,$scenario,$presentation);
    file_put_contents($destination.'/'.$type.'-'.$scenario.'.html',view('documents.pdf',App\Services\DocumentViewModel::prepare($data))->render());
    file_put_contents($destination.'/'.$type.'-'.$scenario.'.pdf',app(App\Services\Documents::class)->render($data));
    echo $type.'-'.$scenario." generated\n";
}
