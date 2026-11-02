<?php
namespace Tests\Feature;

use App\Models\{CommunicationTemplate,User};
use App\Services\{Documents,PdfLetterhead};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PdfLetterheadTest extends TestCase
{
    use RefreshDatabase;
    private function admin(): void
    {
        $this->actingAs(User::create(['username'=>'LetterheadAdmin','name'=>'Test','email'=>'pdf@example.invalid','password'=>'Testing123!','role'=>'admin','must_change_password'=>false]));
    }
    private function picture(bool $footer): string
    {
        $image=imagecreatetruecolor(210,297);
        imagefill($image,0,0,imagecolorallocate($image,255,255,255));
        imagefilledrectangle($image,0,0,209,29,imagecolorallocate($image,0,100,50));
        if ($footer) imagefilledrectangle($image,0,275,209,296,imagecolorallocate($image,180,20,20));
        ob_start(); imagejpeg($image,null,85); $bytes=ob_get_clean(); imagedestroy($image); return $bytes;
    }
    private function settings(): array
    {
        return ['version'=>1,'accent'=>'#243831','issuer_name'=>'Entreprise test','letterhead_enabled'=>1,'letterhead_opacity'=>100,
            'margin_top_mm'=>40,'margin_bottom_mm'=>30,'margin_left_mm'=>15,'margin_right_mm'=>15];
    }
    private function sample(int $lines): array
    {
        return ['title'=>'Devis','number'=>'TEST','draft'=>false,'date'=>'30/09/2026','due'=>'30/10/2026','issuer'=>['name'=>'Entreprise test','address'=>'Abidjan','registration'=>'TEST','email'=>'test@example.invalid','tax_note'=>'','payment_terms'=>''],
            'customer'=>['lastName'=>'Client exemple'],'lines'=>array_fill(0,$lines,['description'=>'Fourniture et pose sur mesure','quantity'=>1,'unit_price'=>25000]),'tax_basis_points'=>0,
            'presentation'=>[...$this->settings(),'letterhead_standard'=>'data:image/jpeg;base64,'.base64_encode($this->picture(true)),'letterhead_continuation'=>'data:image/jpeg;base64,'.base64_encode($this->picture(false))]];
    }
    /** Inspect the actual per-page PDF drawing streams, including image order. */
    private function pages(string $pdf): array
    {
        preg_match_all('~(\d+) 0 obj\s*(.*?)\s*endobj~s',$pdf,$matches,PREG_SET_ORDER); $objects=[]; $pages=[];
        foreach ($matches as $m) $objects[(int)$m[1]]=$m[2];
        foreach ($objects as $object) if (preg_match('~/Type\s*/Page\b~',$object)) {
            $this->assertSame(1,preg_match('~/Contents\s+(\d+) 0 R~',$object,$ref));
            $content=$objects[(int)$ref[1]];
            $this->assertSame(1,preg_match('~stream\r?\n(.*?)\r?\nendstream~s',$content,$stream));
            $pages[]=str_contains($content,'/FlateDecode') ? gzuncompress($stream[1]) : $stream[1];
        }
        return $pages;
    }
    public function test_actual_pdf_uses_standard_on_single_and_last_page_and_underlays_before_text(): void
    {
        $before=glob(storage_path('framework/cache/letterhead-*'));
        $single=app(Documents::class)->render($this->sample(1)); $pages=$this->pages($single);
        $this->assertCount(1,$pages); $this->assertStringContainsString($this->picture(true),$single); $this->assertStringNotContainsString($this->picture(false),$single);
        $multiple=app(Documents::class)->render($this->sample(45)); $pages=$this->pages($multiple); $this->assertGreaterThan(1,count($pages));
        $this->assertStringContainsString($this->picture(true),$multiple); $this->assertStringContainsString($this->picture(false),$multiple);
        $backgrounds=[];
        foreach ($pages as $page) {
            $this->assertSame(1,preg_match('~/(I\d+) Do~',$page,$image)); $backgrounds[]=$image[1];
            $this->assertLessThan(strpos($page,'BT'),strpos($page,$image[0]),'The background must be drawn before opaque text content.');
        }
        $last=array_pop($backgrounds); $this->assertCount(1,array_unique($backgrounds)); $this->assertNotSame($last,$backgrounds[0]);
        $this->assertSame($before,glob(storage_path('framework/cache/letterhead-*')));
    }
    public function test_upload_save_preview_preservation_and_removal(): void
    {
        $this->seed(); $this->admin(); $t=CommunicationTemplate::where('channel','pdf')->where('key','quote')->firstOrFail(); $url='/admin/communications/modeles/'.$t->id;
        $data=[...$this->settings(),'letterhead_standard'=>UploadedFile::fake()->createWithContent('standard.jpg',$this->picture(true)),
            'letterhead_continuation'=>UploadedFile::fake()->createWithContent('suite.jpg',$this->picture(false))];
        $this->post($url,$data)->assertSessionHasNoErrors(); $t->refresh();
        $this->assertTrue($t->options['letterhead_enabled']); $this->assertSame(40.0,(float)$t->options['margin_top_mm']);
        $this->assertSame($this->picture(true),PdfLetterhead::decode($t->options['letterhead_standard'])['bytes']);
        $this->get($url)->assertOk()->assertSee('Fonds de page / filigranes')->assertSee('Document de plusieurs pages');
        $saved=$t->getAttributes();
        $this->post($url.'/apercu',[...$this->settings(),'version'=>2,'preview_length'=>'long'])->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertSame($saved,$t->fresh()->getAttributes());
        $this->post($url,[...$this->settings(),'version'=>2,'margin_top_mm'=>55])->assertSessionHasNoErrors();
        $this->assertSame($t->options['letterhead_standard'],$t->fresh()->options['letterhead_standard']);
        $this->post($url,[...$this->settings(),'version'=>3,'remove_letterhead_standard'=>1])->assertSessionHasErrors('letterhead_enabled');
        $this->post($url,[...$this->settings(),'version'=>3,'letterhead_enabled'=>0,'remove_letterhead_standard'=>1])->assertSessionHasNoErrors();
        $this->assertNull($t->fresh()->options['letterhead_standard']); $this->assertFalse($t->fresh()->options['letterhead_enabled']);
        $this->assertDatabaseCount('commercial_messages',0); $this->assertDatabaseCount('document_archives',0);
    }
    public function test_invalid_images_margins_and_missing_backgrounds_are_rejected(): void
    {
        $this->seed(); $this->admin(); $t=CommunicationTemplate::where('channel','pdf')->firstOrFail(); $url='/admin/communications/modeles/'.$t->id;
        $this->post($url,$this->settings())->assertSessionHasErrors('letterhead_enabled');
        $this->post($url,[...$this->settings(),'letterhead_standard'=>'https://example.invalid/image.jpg'])->assertSessionHasErrors('letterhead_standard');
        $this->post($url,[...$this->settings(),'letterhead_standard'=>UploadedFile::fake()->createWithContent('image.svg','<svg></svg>')])->assertSessionHasErrors('letterhead_standard');
        $this->post($url,[...$this->settings(),'margin_top_mm'=>'40; color:red','margin_left_mm'=>100,'letterhead_opacity'=>0])->assertSessionHasErrors(['margin_top_mm','margin_left_mm','letterhead_opacity']);
        $this->assertSame(1,$t->fresh()->version);
        $this->assertNull(PdfLetterhead::decode('https://example.invalid/image.jpg'));
        $this->assertNull(PdfLetterhead::decode('data:image/jpeg;base64,'.base64_encode('<svg/>')));
    }
    public function test_transparent_png_is_rendered_and_disabled_backgrounds_are_ignored(): void
    {
        $image=imagecreatetruecolor(210,297); imagealphablending($image,false); imagesavealpha($image,true);
        imagefill($image,0,0,imagecolorallocatealpha($image,255,255,255,127));
        imagefilledrectangle($image,0,0,209,20,imagecolorallocatealpha($image,0,100,50,30));
        ob_start(); imagepng($image); $bytes=ob_get_clean(); imagedestroy($image);
        $data=$this->sample(1); $data['presentation']['letterhead_standard']='data:image/png;base64,'.base64_encode($bytes);
        $data['presentation']['letterhead_opacity']=50;
        $pdf=app(Documents::class)->render($data); $this->assertCount(1,$this->pages($pdf)); $this->assertStringContainsString('/SMask',$pdf);
        $data['presentation']['letterhead_enabled']=false;
        $pdf=app(Documents::class)->render($data); $this->assertCount(1,$this->pages($pdf)); $this->assertStringNotContainsString('/SMask',$pdf);
    }
}
