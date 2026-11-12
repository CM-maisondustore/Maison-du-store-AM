<?php
namespace App\Http\Controllers\Staff;
use App\Models\{CommunicationTemplate, Invoice, Quote};
use App\Services\{Audit, DocumentAssets, DocumentLayout, Documents};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mise en page d'un devis particulier, modifiée avant l'envoi (crayon de la page d'envoi).
 * Même éditeur que « Mise en page » ; le résultat est enregistré dans le devis seul, jamais dans le modèle commun.
 */
class QuoteLayoutController
{
    private function editable(Quote $quote): void
    {
        abort_if($quote->status === 'accepted' || Invoice::withTrashed()->where('quote_id', $quote->id)->exists(), 409, 'Devis accepté ou facturé : document verrouillé.');
    }

    /** Réglages de départ : ceux déjà propres au devis, sinon le modèle de devis publié. */
    private function base(Quote $quote): array
    {
        $custom = $quote->document_metadata['presentation_override'] ?? null;
        $presentation = DocumentLayout::presentation('quote');
        $options = $custom['options'] ?? \Illuminate\Support\Arr::except($presentation, ['introduction', 'template_version', 'global_template_version']);
        return ['options' => [...DocumentLayout::defaults(), ...$options, 'inherit_global' => false], 'body' => $custom['body'] ?? ($presentation['introduction'] ?? '')];
    }

    public function edit(Quote $quote)
    {
        $this->editable($quote);
        $template = CommunicationTemplate::where('channel', 'pdf')->where('key', 'quote')->firstOrFail();
        $base = $this->base($quote);
        return view('staff.document-layout', [
            'template' => $template, 'options' => DocumentAssets::resolve($base['options']), 'draft' => null,
            'quoteLayout' => $quote, 'quoteBody' => $base['body'], 'templates' => collect(), 'revisions' => collect(),
        ]);
    }

    public function save(Request $r, Quote $quote)
    {
        $this->editable($quote);
        $r->validate(['edit_version' => 'required|integer']);
        $data = DocumentLayout::validate($r, $this->base($quote)['options']);
        DB::transaction(function () use ($r, $quote, $data) {
            $q = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($q->edit_version !== $r->integer('edit_version')) throw ValidationException::withMessages(['version' => 'Ce devis a changé. Rechargez la page avant d’enregistrer.']);
            $options = [...DocumentAssets::store($data['options']), 'inherit_global' => false];
            $q->update(['document_metadata' => [...($q->document_metadata ?? []), 'presentation_override' => ['options' => $options, 'body' => $data['body'], 'saved_at' => now()->toIso8601String()]],
                'edit_version' => $q->edit_version + 1]);
            Audit::record('mise_en_page_devis_personnalisee', $q);
        });
        return redirect()->route('staff.quotes.layout', $quote)->with('success', 'Mise en page enregistrée pour ce devis seulement. Le modèle commun ne change pas.');
    }

    public function preview(Request $r, Quote $quote, Documents $documents)
    {
        $this->editable($quote);
        $data = DocumentLayout::validate($r, $this->base($quote)['options']);
        $pdf = $documents->render($documents->quoteData($quote, ['options' => [...$data['options'], 'inherit_global' => false], 'body' => $data['body']]));
        return response($pdf)->header('Content-Type', 'application/pdf')->header('Cache-Control', 'private, no-store');
    }

    public function reset(Quote $quote)
    {
        $this->editable($quote);
        DB::transaction(function () use ($quote) {
            $q = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $q->update(['document_metadata' => \Illuminate\Support\Arr::except($q->document_metadata ?? [], ['presentation_override']), 'edit_version' => $q->edit_version + 1]);
            Audit::record('mise_en_page_devis_reinitialisee', $q);
        });
        return redirect()->route('staff.quotes.layout', $quote)->with('success', 'Ce devis reprend le modèle de devis publié.');
    }
}
