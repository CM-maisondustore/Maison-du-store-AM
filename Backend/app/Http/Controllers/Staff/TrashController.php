<?php

namespace App\Http\Controllers\Staff;

use App\Services\{Audit, Trash};
use Illuminate\Http\Request;

/** Archives et corbeille (accès admin) : devis, catalogue, clients, rendez-vous, messagerie, factures. */
class TrashController
{
    public function index()
    {
        $groups = [];
        foreach (Trash::TYPES as $type => [$class, $label]) {
            $groups[$type] = ['label' => $label, 'items' => $class::inTrash()->with('deletedBy')->latest('deleted_at')->limit(100)->get()];
        }
        return view('staff.trash', ['groups' => $groups]);
    }

    public function archive(string $type, int $id)
    {
        $m = Trash::find($type, $id);
        $m->forceFill(['archived_at' => now()])->saveQuietly();
        Audit::record('element_archive', $m);
        return back()->with('success', 'Élément archivé : il n’apparaît plus dans la liste courante (onglet « Archivés »).');
    }

    public function unarchive(string $type, int $id)
    {
        $m = Trash::find($type, $id);
        $m->forceFill(['archived_at' => null])->saveQuietly();
        Audit::record('element_desarchive', $m);
        return back()->with('success', 'Élément sorti des archives.');
    }

    public function destroy(Request $r, string $type, int $id)
    {
        $m = Trash::find($type, $id);
        if ($reason = Trash::blocker($m)) return back()->withErrors(['trash' => $reason]);
        Trash::moveToTrash($m, $r->user()->id);
        Audit::record('element_corbeille', $m);
        return redirect()->route('staff.'.Trash::TYPES[$type][2], ['etat' => 'corbeille'])
            ->with('success', 'Élément mis à la corbeille. Restaurable pendant '.Trash::DAYS.' jours.');
    }

    public function restore(string $type, int $id)
    {
        $m = Trash::find($type, $id, true);
        if ($error = Trash::restore($m)) return back()->withErrors(['trash' => $error]);
        Audit::record('element_restaure', $m);
        return back()->with('success', 'Élément restauré : « '.Trash::title($m).' ».');
    }

    public function forceDestroy(string $type, int $id)
    {
        $m = Trash::find($type, $id, true);
        $title = Trash::title($m);
        if (!Trash::purge($m)) return back()->withErrors(['trash' => 'Suppression définitive impossible : cet élément est encore lié à d’autres dossiers.']);
        Audit::record('element_supprime', $m);
        return back()->with('success', '« '.$title.' » supprimé définitivement.');
    }
}
