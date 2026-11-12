<?php
namespace App\Http\Controllers\Staff;
use App\Models\{CommunicationTemplate,DocumentIssue,Quote,QuotePayment};
use App\Services\{DocumentLayout,DocumentAssets,DocumentPreview,Documents,DocumentIssues,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentLayoutController
{
    private function template(string $type): CommunicationTemplate
    {
        abort_unless(in_array($type,['global','quote','invoice','receipt']),404);
        return CommunicationTemplate::where('channel','pdf')->where('key',$type)->firstOrFail();
    }
    public function edit(string $type='global')
    {
        $template=$this->template($type); $draft=$template->layout_draft;
        $options=DocumentAssets::resolve([...DocumentLayout::defaults(),...($draft['options'] ?? $template->options ?? [])]);
        if (!empty($options['inherit_global'])) {
            $global=DocumentLayout::presentation('global');
            if (($global['renderer_version'] ?? 1)===2) $options=DocumentAssets::resolve([...DocumentLayout::defaults(),...$global,'inherit_global'=>true]);
        }
        return view('staff.document-layout',compact('template','options','draft')+[
            'templates'=>CommunicationTemplate::where('channel','pdf')->get(['id','version']),
            'revisions'=>DB::table('document_template_revisions')->where('communication_template_id',$template->id)->latest('id')->get(['id','version','created_at'])]);
    }
    public function save(Request $r,string $type)
    {
        $template=$this->template($type);
        $r->validate(['action'=>'required|in:draft,publish','apply_all'=>'sometimes|boolean','target_versions'=>'sometimes|array','target_versions.*'=>'integer|min:1']);
        $data=DocumentLayout::validate($r,$template->layout_draft['options'] ?? $template->options ?? []);
        DB::transaction(function () use ($r,$template,$data,$type) {
            // Consistent locking order also serializes global publication against individual editing.
            $templates=CommunicationTemplate::where('channel','pdf')->orderBy('id')->lockForUpdate()->get();
            $locked=$templates->firstWhere('id',$template->id);
            if ($locked->version!==$r->integer('version')) throw ValidationException::withMessages(['version'=>'Ce modèle a changé. Rechargez la page avant d’enregistrer.']);
            $data['options']=DocumentAssets::store($data['options']);
            if ($r->input('action')==='draft') $locked->update(['layout_draft'=>$data,'published_version'=>$locked->published_version ?? $locked->version,'version'=>$locked->version+1]);
            else {
                if ($type==='global') foreach ($templates as $current) DocumentLayout::revision($current);
                DocumentLayout::revision($locked);
                $locked->update([...$data,'layout_draft'=>null,'published_version'=>$locked->version+1,'version'=>$locked->version+1]); DocumentLayout::revision($locked);
                if ($type==='global' && $r->boolean('apply_all')) foreach ($templates as $other) if ($other->id!==$locked->id) {
                    if ((int)$r->input('target_versions.'.$other->id)!==$other->version) throw ValidationException::withMessages(['version'=>'Un modèle de document a changé. Rechargez la page avant publication globale.']);
                    DocumentLayout::revision($other);
                    $other->update(['options'=>[...($other->options ?? []),'inherit_global'=>true],'published_version'=>$other->version+1,'version'=>$other->version+1]); DocumentLayout::revision($other);
                }
            }
            Audit::record($r->input('action')==='publish'?'mise_en_page_publiee':'mise_en_page_brouillon',$locked);
        });
        return back()->with('success',$r->input('action')==='publish'?'Modèle publié pour les prochaines éditions. Les archives restent inchangées.':'Brouillon enregistré. Le modèle publié reste actif.');
    }
    public function preview(Request $r,string $type,Documents $documents)
    {
        $template=$this->template($type);
        $r->validate(['scenario'=>'required|in:single,four,eight,twenty,thirty,store,discount,negotiated,individual','preview_type'=>'required|in:quote,invoice,receipt']);
        $data=DocumentLayout::validate($r,$template->layout_draft['options'] ?? $template->options ?? []);
        $presentation=[...$data['options'],'introduction'=>$data['body']];
        if ($type!=='global' && !empty($presentation['inherit_global'])) {
            $global=\App\Services\CommunicationTemplates::presentation('global');
            if (($global['renderer_version'] ?? 1)===2) $presentation=[...$global,'introduction'=>$data['body'] ?: $global['introduction']];
        }
        $pdf=$documents->render(DocumentPreview::data($r->input('preview_type'),$r->input('scenario'),$presentation));
        return response($pdf)->header('Content-Type','application/pdf')->header('Cache-Control','private, no-store');
    }
    public function restore(Request $r,string $type,int $revision)
    {
        $r->validate(['version'=>'required|integer']); $template=$this->template($type);
        DB::transaction(function () use ($r,$template,$revision) {
            $locked=CommunicationTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
            if ($locked->version!==$r->integer('version')) throw ValidationException::withMessages(['version'=>'Rechargez le modèle avant restauration.']);
            $row=DB::table('document_template_revisions')->where('id',$revision)->where('communication_template_id',$locked->id)->first(); abort_unless($row,404);
            $locked->update(['layout_draft'=>json_decode($row->snapshot,true,512,JSON_THROW_ON_ERROR),'published_version'=>$locked->published_version ?? $locked->version,'version'=>$locked->version+1]); Audit::record('modele_repris_en_brouillon',$locked);
        });
        return back()->with('success','Version reprise en brouillon. Vérifiez l’aperçu avant publication.');
    }
    public function receipt(Quote $quote,QuotePayment $payment,Documents $documents)
    {
        $issue=DocumentIssues::receipt($quote,$payment,$documents);
        return response($issue->archive->contents())->header('Content-Type','application/pdf')->header('Content-Disposition','attachment; filename="recu-'.$issue->snapshot['number'].'.pdf"');
    }
    public function archive(DocumentIssue $issue)
    {
        return response($issue->archive->contents())->header('Content-Type','application/pdf')->header('Content-Disposition','attachment; filename="document-'.$issue->id.'.pdf"');
    }
    public function metadata(Request $r,Quote $quote)
    {
        $data=$r->validate(['edit_version'=>'required|integer','object'=>'nullable|string|max:1000','rooms'=>'nullable|array|max:100','rooms.*'=>'nullable|string|max:100']);
        DB::transaction(function () use ($quote,$data) {
            $q=Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            abort_if($q->status==='accepted' || \App\Models\Invoice::where('quote_id',$q->id)->exists(),409,'Devis accepté ou facturé : document verrouillé.');
            if ($q->edit_version!==$data['edit_version']) throw ValidationException::withMessages(['edit_version'=>'Rechargez le devis avant modification.']);
            $q->update(['document_metadata'=>[...($q->document_metadata ?? []),'object'=>$data['object'] ?? '', 'rooms'=>$data['rooms'] ?? []],'edit_version'=>$q->edit_version+1]);
            Audit::record('presentation_devis_modifiee',$q);
        });
        return back()->with('success','Présentation enregistrée. Les éditions précédentes restent accessibles.');
    }
}
