<?php
namespace App\Http\Controllers\Staff;
use App\Models\SiteContent;
use App\Services\{Audit, MediaStorage};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class SiteContentController
{
    public function index() { return view('staff.contents', ['contents' => SiteContent::with('media')->orderBy('kind')->orderBy('position')->orderBy('id')->paginate(16)]); }
    public function create() { return view('staff.content', ['content' => new SiteContent(['kind' => 'portfolio', 'position' => 0, 'published' => false])]); }
    public function edit(SiteContent $content) { return view('staff.content', compact('content')); }
    private function data(Request $request): array
    {
        $data = $request->validate([...MediaStorage::rules(), 'title' => 'required|string|max:150', 'description' => 'nullable|string|max:3000', 'published' => 'required|boolean', 'position' => 'required|integer|min:0|max:9999']);
        return collect($data)->only(['title','description','published','position'])->all();
    }
    public function store(Request $request)
    {
        $data = $this->data($request);
        $content = DB::transaction(function () use ($data, $request) {
            $content = SiteContent::create([...$data, 'kind' => 'portfolio', 'slot' => 'portfolio-'.Str::uuid()]);
            MediaStorage::apply($content, $request); Audit::record('contenu_cree', $content); return $content;
        });
        return redirect()->route('staff.contents.edit', $content)->with('success', 'Réalisation et image enregistrées.');
    }
    public function update(Request $request, SiteContent $content)
    {
        $data = $this->data($request);
        DB::transaction(function () use ($data, $request, $content) {
            $locked = SiteContent::whereKey($content->id)->lockForUpdate()->firstOrFail();
            $locked->fill($data); MediaStorage::apply($locked, $request); Audit::record('contenu_modifie', $locked);
        });
        return redirect()->route('staff.contents.edit', $content)->with('success', 'Image, textes et visibilité enregistrés.');
    }
}
