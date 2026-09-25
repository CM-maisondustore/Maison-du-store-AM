<?php
namespace App\Http\Controllers\Staff;
use App\Models\{Quote,Contact,Invoice,User,QuotePayment};
use App\Services\{StaffQuoteData,QuoteReceipts,Pricing,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class QuoteFormController
{
    private function access(Request $r,Quote $quote,bool $edit=false): void
    {
        abort_unless($r->user()->isAdmin() || $quote->agent_id===$r->user()->id,403);
        if ($edit) {
            abort_if($quote->status==='accepted' || Invoice::where('quote_id',$quote->id)->exists(),409,'Devis accepté ou facturé : document verrouillé.');
            abort_unless($r->user()->isAdmin() || in_array($quote->status,['pending','draft']),403);
        }
    }
    private function form(Quote $quote)
    {
        return view('staff.quote-form',['quote'=>$quote,'catalogue'=>StaffQuoteData::catalogue(),'agents'=>User::where('role','agent')->where('active',true)->get()]);
    }
    public function create() { return $this->form(new Quote(['contact'=>[],'dimensions'=>[],'edit_version'=>0,'submission_token'=>(string)Str::uuid()])); }
    public function edit(Request $r,Quote $quote) { $this->access($r,$quote,true); return $this->form($quote); }
    private function contact(array $data,?Quote $quote=null): Contact
    {
        $email=mb_strtolower(trim($data['email'] ?? ''));
        if ($quote?->contact_id && $email===mb_strtolower(trim($quote->contact['email'] ?? ''))) return Contact::findOrFail($quote->contact_id);
        $values=['kind'=>'client','name'=>trim(($data['firstName'] ?? '').' '.$data['lastName']),'phone'=>$data['phone'],'address'=>trim($data['address'].' '.($data['city'] ?? ''))];
        $contact=$email ? Contact::firstOrCreate(['email'=>$email],$values) : Contact::create([...$values,'email'=>null]);
        if ($contact->archived_at || $contact->kind!=='client') throw ValidationException::withMessages(['contact.email'=>'Ce dossier est archivé ou ne correspond pas à un client. Admin-CM doit vérifier le dossier.']);
        return $contact;
    }
    public function store(Request $r)
    {
        $data=StaffQuoteData::validate($r); $payment=QuoteReceipts::validate($r);
        $token=$r->input('submission_token');
        $previous=Quote::where('submission_token',$token)->first();
        if ($previous) { abort_unless($previous->created_by===$r->user()->id,403); return redirect()->route('staff.quotes.show',$previous); }
        try {
            $quote=DB::transaction(function () use ($r,$data,$payment,$token) {
                $quote=Quote::create([...$data,'contact_id'=>$this->contact($data['contact'])->id,'reference'=>(string)Str::uuid(),'source'=>'interne','status'=>'draft','submission_token'=>$token,'created_by'=>$r->user()->id,'agent_id'=>$r->user()->isAdmin() ? ($data['agent_id'] ?? null) : $r->user()->id]);
                QuoteReceipts::record($quote,$payment,$r->user()->id); Audit::record('devis_interne_cree',$quote); return $quote;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $quote=Quote::where('submission_token',$token)->where('created_by',$r->user()->id)->first(); if (!$quote) throw $e;
        }
        return redirect()->route('staff.quotes.show',$quote)->with('success','Devis enregistré en brouillon avec ses fenêtres et ses paiements.');
    }
    public function update(Request $r,Quote $quote)
    {
        $this->access($r,$quote,true); $data=StaffQuoteData::validate($r); $payment=QuoteReceipts::validate($r);
        DB::transaction(function () use ($r,$quote,$data,$payment) {
            $locked=Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail(); $this->access($r,$locked,true);
            if ($locked->edit_version!==(int)$r->input('edit_version')) throw ValidationException::withMessages(['edit_version'=>'Ce devis a été modifié dans une autre fenêtre. Rechargez-le avant de reprendre la saisie.']);
            if ($locked->receivedAmount()>Pricing::totals($data['lines'],$data['tax_basis_points'])['total']) throw ValidationException::withMessages(['lines'=>'Le total ne peut pas être inférieur aux montants déjà reçus.']);
            $locked->fill([...$data,'contact_id'=>$this->contact($data['contact'],$locked)->id,'status'=>'draft','edit_version'=>$locked->edit_version+1]);
            $locked->save(); QuoteReceipts::record($locked,$payment,$r->user()->id); Audit::record('formulaire_devis_modifie',$locked);
        });
        return redirect()->route('staff.quotes.show',$quote)->with('success','Devis et paiements enregistrés.');
    }
    public function receipt(Request $r,Quote $quote)
    {
        $this->access($r,$quote); $r->validate(['receipt_amount'=>'required|integer|min:1']); $data=QuoteReceipts::validate($r);
        DB::transaction(function () use ($r,$quote,$data) {
            $locked=Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail(); $this->access($r,$locked);
            abort_if(in_array($locked->status,['cancelled','rejected']),409,'Devis annulé ou refusé.');
            QuoteReceipts::record($locked,$data,$r->user()->id);
        });
        return back()->with('success','Paiement enregistré dans l’historique du devis.');
    }
    public function voidReceipt(Request $r,Quote $quote,QuotePayment $payment)
    {
        abort_unless($r->user()->isAdmin() && $payment->quote_id===$quote->id,403);
        $data=$r->validate(['void_reason'=>'required|string|min:5|max:1000']);
        DB::transaction(function () use ($quote,$payment,$data) {
            Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $locked=QuotePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (!$locked->voided_at) { $locked->update([...$data,'voided_at'=>now()]); Audit::record('paiement_devis_annule',$locked); }
        });
        return back()->with('success','Saisie annulée, historique conservé.');
    }
}
