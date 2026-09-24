<?php
namespace App\Http\Controllers\Staff;
use App\Models\Product;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ProductController
{
    public function index() { return view('staff.products', ['products' => Product::orderBy('id')->get()]); }
    public function create() { return view('staff.product', ['product' => new Product(['details' => []])]); }
    public function edit(Product $product) { return view('staff.product', compact('product')); }
    public function store(Request $r) { $product = Product::create($this->data($r)); Audit::record('produit_cree', $product); return redirect()->route('staff.products.edit', $product)->with('success', 'Produit créé.'); }
    public function update(Request $r, Product $product) { $product->update($this->data($r, $product)); Audit::record('produit_modifie', $product); return back()->with('success', 'Catalogue enregistré.'); }
    private function data(Request $r, ?Product $product = null): array
    {
        $data = $r->validate(['reference' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product)], 'name' => 'required|string|max:150', 'type' => 'required|in:store,rideau', 'category' => 'required|string|max:150', 'description' => 'required|string|max:3000', 'quoteModel' => 'required|string|max:150', 'features' => 'nullable|array', 'features.*' => 'in:occultant,tamisant,motorise,decoratif', 'colors' => 'nullable|array', 'colors.*' => 'in:blanc,ecru,beige,taupe,gris,anthracite,bleu,vert,bordeaux', 'specs_text' => 'nullable|string|max:3000', 'published' => 'nullable|boolean', 'available' => 'nullable|boolean', 'featured' => 'nullable|boolean']);
        $specs = [];
        foreach (preg_split('/\R/', $data['specs_text'] ?? '') as $line) if (str_contains($line, ':')) { [$label, $value] = explode(':', $line, 2); $specs[] = ['label' => trim($label), 'value' => trim($value)]; }
        return ['reference' => $data['reference'], 'published' => $r->boolean('published'), 'details' => [...collect($data)->only(['name', 'type', 'category', 'description', 'quoteModel'])->all(), 'features' => $data['features'] ?? [], 'colors' => $data['colors'] ?? [], 'specs' => $specs, 'available' => $r->boolean('available'), 'featured' => $r->boolean('featured'), 'price' => 'Prix']];
    }
}
