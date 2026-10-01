<?php
namespace App\Services;
use App\Models\CommunicationTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DocumentLayout
{
    public const COLORS=['accent'=>'Or principal','secondary_color'=>'Or clair','text_color'=>'Texte principal','muted_color'=>'Texte secondaire','stripe_color'=>'Lignes alternées'];
    public const TOGGLES=['show_logo'=>'Logo','show_name'=>'Nom de l’entreprise','show_activities'=>'Activités','show_title'=>'Type de document','show_cover'=>'Couverture séparée (devis et factures)','striped_rows'=>'Alternance des lignes','table_borders'=>'Bordures des tableaux','show_conditions'=>'Conditions commerciales','show_client_signature'=>'Signature client','show_company_signature'=>'Signature entreprise','show_company_details'=>'Coordonnées en pied de document','show_page_number'=>'Pagination'];
    public static function defaults(): array
    {
        return ['renderer_version'=>2,'accent'=>'#C9A037','secondary_color'=>'#F5ECD4','text_color'=>'#111111','muted_color'=>'#555555','stripe_color'=>'#F2F2F2',
            'font_family'=>'DejaVu Sans','font_size'=>10,'activities'=>'Rideaux sur mesure · Stores · Motorisation · Décoration intérieure',
            'show_logo'=>true,'show_name'=>true,'show_activities'=>true,'show_title'=>true,'show_cover'=>true,'striped_rows'=>true,'table_borders'=>false,
            'show_conditions'=>true,'show_client_signature'=>true,'show_company_signature'=>true,'show_company_details'=>true,'show_page_number'=>true,
            'table_header'=>'light','total_style'=>'gold','subtotal_style'=>'light','discount_style'=>'light','deposit_style'=>'light','balance_style'=>'light',
            'margin_top_mm'=>25,'margin_bottom_mm'=>25,'margin_left_mm'=>15,'margin_right_mm'=>15,'letterhead_enabled'=>false,'letterhead_opacity'=>100,
            'issuer_email'=>config('workspace.issuer.email'),'issuer_phone'=>'','issuer_whatsapp'=>config('workspace.whatsapp_number'),'issuer_rccm'=>'','issuer_ncc'=>'','issuer_website'=>'www.maisondustoream.com'];
    }
    public static function validate(Request $r, array $saved): array
    {
        $rules=['version'=>'required|integer|min:1','name'=>'required|string|max:150','body'=>'nullable|string|max:10000','inherit_global'=>'sometimes|boolean',
            'font_family'=>'required|in:DejaVu Sans,DejaVu Serif','font_size'=>'required|integer|min:8|max:12','activities'=>'nullable|string|max:300',
            'footer'=>'nullable|string|max:2000','signature'=>'nullable|string|max:1000','issuer_name'=>'required|string|max:150','issuer_email'=>'sometimes|required|email:rfc|max:254',
            'issuer_address'=>'nullable|string|max:1000','issuer_registration'=>'nullable|string|max:300','issuer_tax_note'=>'nullable|string|max:1000','issuer_payment_terms'=>'nullable|string|max:2000'];
        foreach (self::COLORS as $key=>$label) $rules[$key]='required|regex:/^#[0-9a-fA-F]{6}$/';
        foreach (self::TOGGLES as $key=>$label) $rules[$key]='required|boolean';
        foreach (['issuer_phone','issuer_whatsapp','issuer_rccm','issuer_ncc','issuer_website'] as $key) $rules[$key]='nullable|string|max:150';
        foreach (['table_header','total_style','subtotal_style','discount_style','deposit_style','balance_style'] as $key) $rules[$key]='required|in:gold,light,plain';
        foreach (['logo','signature_image','stamp_image'] as $key) {
            $rules[$key]=['nullable','file','image','mimes:png,jpg,jpeg','max:512','dimensions:max_width=2000,max_height=2000'];
            $rules['remove_'.$key]='sometimes|boolean';
        }
        $data=$r->validate($rules); $options=[...self::defaults(),...$saved];
        foreach ($data as $key=>$value) if (!in_array($key,['version','name','body','logo','signature_image','stamp_image']) && !str_starts_with($key,'remove_')) $options[$key]=$value ?? '';
        foreach (self::TOGGLES as $key=>$label) $options[$key]=$r->boolean($key);
        $options['inherit_global']=$r->boolean('inherit_global');
        foreach (['logo','signature_image','stamp_image'] as $key) {
            if ($r->boolean('remove_'.$key)) $options[$key]=null;
            if ($r->hasFile($key)) {
                $bytes=$r->file($key)->get(); $size=@getimagesizefromstring($bytes);
                if (!$size || !in_array($size['mime'],['image/png','image/jpeg'])) throw ValidationException::withMessages([$key=>'Utilisez une image PNG ou JPEG.']);
                $options[$key]='data:'.$size['mime'].';base64,'.base64_encode($bytes);
            }
        }
        $options=[...$options,...PdfLetterhead::options($r,$options),'renderer_version'=>2];
        return ['name'=>$data['name'],'body'=>$data['body'] ?? '', 'options'=>$options];
    }
    public static function revision(CommunicationTemplate $template): void
    {
        $options=$template->options ?? []; $body=$template->body;
        if (!empty($options['inherit_global'])) {
            $resolved=self::presentation($template->key);
            if (($resolved['renderer_version'] ?? 1)===2) { $options=[...$resolved,'inherit_global'=>false]; $body=$resolved['introduction']; }
        }
        DB::table('document_template_revisions')->insertOrIgnore(['communication_template_id'=>$template->id,'version'=>$template->published_version ?? $template->version,
            'snapshot'=>json_encode(['name'=>$template->name,'body'=>$body,'options'=>$options],JSON_THROW_ON_ERROR),'created_at'=>now()]);
    }
    public static function presentation(string $key): array
    {
        $t=CommunicationTemplates::get('pdf',$key); $options=$t->options ?? [];
        $introduction=$t->body ?? '';
        $global=CommunicationTemplate::where('channel','pdf')->where('key','global')->first();
        if ($key!=='global' && !empty($options['inherit_global']) && ($global?->options['renderer_version'] ?? 1)===2) {
            $options=[...$global->options,'global_template_version'=>$global->published_version ?? $global->version];
            if ($introduction==='') $introduction=$global->body;
        }
        return [...$options,'introduction'=>$introduction,'template_version'=>$t->published_version ?? $t->version];
    }
}
