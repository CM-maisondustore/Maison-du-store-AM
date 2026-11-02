<?php
namespace App\Http\Controllers\Staff;

use App\Models\Quote;
use App\Services\{Audit,Quotes\QuoteFinancials,Quotes\QuoteWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};

class QuoteWorkflowController
{
    public function update(Request $request, Quote $quote)
    {
        abort_unless($request->user()->isAdmin(),403);
        $data=$request->validate(['stage'=>['required',Rule::in(array_keys(QuoteWorkflow::STAGES))],'edit_version'=>'required|integer|min:0']);
        DB::transaction(function() use ($quote,$data) {
            $locked=Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($locked->edit_version!==(int)$data['edit_version']) throw ValidationException::withMessages(['stage'=>'Rechargez le devis : il a été modifié.']);
            if ($locked->status!=='accepted') throw ValidationException::withMessages(['stage'=>'L’accord du client doit être enregistré avant le démarrage.']);
            $financial=QuoteFinancials::forQuote($locked);
            if (!$financial['productionAuthorized']) throw ValidationException::withMessages(['stage'=>'L’acompte requis doit être reçu et enregistré avant le démarrage de la fabrication.']);
            if ($data['stage']==='paid' && $financial['outstanding']!==0) throw ValidationException::withMessages(['stage'=>'Le solde doit être reçu et enregistré avant de solder le dossier.']);
            $locked->update(['configuration'=>[...($locked->configuration ?? []),'workflow_stage'=>$data['stage']],'edit_version'=>$locked->edit_version+1]);
            Audit::record('avancement_devis_modifie',$locked);
        });
        return back()->with('success','Avancement enregistré.');
    }
}
