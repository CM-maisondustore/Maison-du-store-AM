<?php
namespace App\Http\Controllers\Staff;
use App\Models\{CommunicationTemplate,CommunicationRule,CommunicationCheck};
use App\Services\{CommunicationTemplates,MailConnection,Documents,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CommunicationController
{
    public function index(MailConnection $mail)
    {
        return view('staff.communications',['templates'=>CommunicationTemplate::orderBy('channel')->orderBy('id')->get(),
            'rules'=>CommunicationRule::all(),'verified'=>$mail->verified(),'checks'=>CommunicationCheck::all()->keyBy('key')]);
    }
    public function edit(CommunicationTemplate $template)
    {
        if ($template->channel==='pdf' && (in_array($template->key,['global','receipt']) || (CommunicationTemplates::presentation($template->key)['renderer_version'] ?? 1)===2)) return redirect()->route('staff.layouts.edit',$template->key);
        return view('staff.communication-template',compact('template'));
    }
    private function data(Request $r, CommunicationTemplate $template): array
    {
        $data=$r->validate(['version'=>'required|integer|min:1','subject'=>'nullable|string|max:200|not_regex:/[\r\n]/','body'=>'nullable|string|max:10000']);
        $data['body']=$data['body'] ?? ''; $data['subject']=$data['subject'] ?? '';
        if ($template->channel!=='pdf') {
            if (!$data['body'] || !$data['subject']) throw ValidationException::withMessages(['body'=>'Renseignez un objet et un message.']);
            if ($template->channel==='whatsapp' && mb_strlen($data['body'])>3000) throw ValidationException::withMessages(['body'=>'Limitez le message WhatsApp à 3000 caractères.']);
            CommunicationTemplates::validateText($data['subject']); CommunicationTemplates::validateText($data['body']);
        } else {
            $options=$r->validate(['accent'=>'required|regex:/^#[0-9a-fA-F]{6}$/','footer'=>'nullable|string|max:2000','signature'=>'nullable|string|max:1000',
                'issuer_name'=>'required|string|max:150','issuer_address'=>'nullable|string|max:1000','issuer_registration'=>'nullable|string|max:300',
                'issuer_tax_note'=>'nullable|string|max:1000','issuer_payment_terms'=>'nullable|string|max:2000',
                'logo'=>['nullable','file','image','mimes:png,jpg,jpeg','max:512','dimensions:max_width=2000,max_height=2000'],'remove_logo'=>'sometimes|boolean']);
            unset($options['logo'],$options['remove_logo']); $options=array_map(fn($v)=>$v ?? '',$options);
            $options['logo']=$template->options['logo'] ?? null;
            if ($r->boolean('remove_logo')) $options['logo']=null;
            if ($r->hasFile('logo')) {
                $bytes=$r->file('logo')->get(); $size=getimagesizefromstring($bytes);
                if (!$size || !in_array($size['mime'],['image/png','image/jpeg'])) throw ValidationException::withMessages(['logo'=>'Utilisez une image PNG ou JPEG.']);
                $options['logo']='data:'.$size['mime'].';base64,'.base64_encode($bytes);
            }
            $data['options']=[...$options,...\App\Services\PdfLetterhead::options($r,$template->options ?? [])];
        }
        return $data;
    }
    public function update(Request $r, CommunicationTemplate $template)
    {
        if ($template->channel==='pdf' && (CommunicationTemplates::presentation($template->key)['renderer_version'] ?? 1)===2) throw ValidationException::withMessages(['version'=>'Utilisez Mise en page pour modifier ce modèle publié.']);
        $data=$this->data($r,$template);
        DB::transaction(function () use($template,$data) {
            $locked=CommunicationTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
            if ($locked->version !== (int)$data['version']) throw ValidationException::withMessages(['version'=>'Ce modèle a été modifié. Rechargez la page avant d’enregistrer.']);
            if ($locked->channel==='pdf') \App\Services\DocumentLayout::revision($locked);
            $locked->update([...$data,'version'=>$locked->version+1,...($locked->channel==='pdf'?['published_version'=>$locked->version+1]:[])]);
            if ($locked->channel==='pdf') \App\Services\DocumentLayout::revision($locked);
            Audit::record('modele_modifie',$locked);
        });
        return back()->with('success','Modèle enregistré. Les documents déjà archivés restent inchangés.');
    }
    public function preview(Request $r, CommunicationTemplate $template, Documents $documents)
    {
        $data=$this->data($r,$template);
        if ($template->channel==='pdf') {
            $r->validate(['preview_length'=>'sometimes|in:short,long']);
            $lines=$r->input('preview_length')==='long'
                ? array_map(fn($i)=>['description'=>'Fenêtre '.$i.' — confection, fournitures et installation sur mesure (exemple)','quantity'=>1,'unit_price'=>25000],range(1,45))
                : [['description'=>'Confection et pose sur mesure — exemple','quantity'=>2,'unit_price'=>25000]];
            $presentation=[...$data['options'],'introduction'=>$data['body']];
            return response($documents->render(['presentation'=>$presentation,'issuer'=>CommunicationTemplates::issuer($presentation),'title'=>$template->key==='quote'?'Devis':'Facture','number'=>'APERÇU','draft'=>true,'date'=>now()->format('d/m/Y'),'due'=>now()->addDays(30)->format('d/m/Y'),
                'customer'=>['firstName'=>'Client','lastName'=>'Exemple','address'=>'Adresse de démonstration','email'=>'exemple@example.invalid'],
                'lines'=>$lines,'tax_basis_points'=>0]))->header('Content-Type','application/pdf')->header('Content-Disposition','inline; filename="apercu.pdf"');
        }
        $values=['prenom'=>'Awa','nom'=>'Awa Exemple','reference'=>'DEV-EXEMPLE','montant'=>'50 000','acompte'=>'25 000','date'=>now()->addDay()->format('d/m/Y'),'heure'=>'10:00','adresse'=>'Adresse de démonstration, Abidjan','entreprise'=>'Maison du Store AM','statut'=>'confirmé'];
        return view('staff.communication-preview',['subject'=>CommunicationTemplates::render($data['subject'],$values),'body'=>CommunicationTemplates::render($data['body'],$values)]);
    }
    public function rule(Request $r, CommunicationRule $rule, MailConnection $mail)
    {
        $data=$r->validate(['mode'=>'required|in:off,draft,automatic','delay_hours'=>'required|integer|min:1|max:168']);
        if ($data['mode']==='automatic' && !$mail->verified()) throw ValidationException::withMessages(['mode'=>'Vérifiez d’abord la connexion SMTP. Aucun envoi automatique n’est activé.']);
        DB::transaction(function () use($rule,$data) {
            $locked=CommunicationRule::whereKey($rule->id)->lockForUpdate()->firstOrFail();
            if ($data['mode']!=='off' && ($locked->mode==='off' || !$locked->activated_at)) $data['activated_at']=now();
            $locked->update($data); Audit::record('automatisation_modifiee',$locked);
        });
        return back()->with('success','Règle enregistrée. Les promotions restent des envois manuels.');
    }
    public function connection(MailConnection $mail)
    {
        $ok=$mail->check();
        return $ok ? back()->with('success','Connexion SMTP vérifiée sans envoi de message.')
            : back()->withErrors(['mail'=>'Connexion SMTP non validée. Vérifiez les paramètres Hostinger Email dans la configuration privée.']);
    }
    public function process(\App\Services\CommunicationAutomation $automation)
    {
        $result=$automation->process(50);
        return back()->with('success',"Traitement terminé : {$result['drafts']} brouillon(s), {$result['sent']} envoi(s), {$result['blocked']} message(s) à vérifier.");
    }
}
