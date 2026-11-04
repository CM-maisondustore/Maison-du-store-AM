<?php

namespace App\Http\Controllers\Staff;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:150', 'published' => 'nullable|in:0,1',
            'available' => 'nullable|in:0,1', 'type' => 'nullable|in:store,rideau',
            'universe' => 'nullable|in:stores_rideaux,electromenager,meubles_bureau,meubles_domicile',
        ]);
        $query = Product::query();
        if (!empty($filters['q'])) {
            $query->where(fn ($q) => $q->where('reference', 'like', '%'.$filters['q'].'%')
                ->orWhere('details->name', 'like', '%'.$filters['q'].'%'));
        }
        if ($request->filled('published')) $query->where('published', $request->boolean('published'));
        if ($request->filled('available')) $query->where('details->available', $request->boolean('available'));
        if ($request->filled('type')) $query->where('details->type', $filters['type']);
        // Les produits créés avant les univers n'ont pas ce champ : ce sont des stores et des rideaux.
        if ($request->filled('universe')) $filters['universe'] === 'stores_rideaux' ? $query->whereIn('details->type', ['store', 'rideau']) : $query->where('details->universe', $filters['universe']);
        $catalogueVisible = SiteSetting::catalogueVisible();
        return view('staff.products', [
            'catalogueVisible' => $catalogueVisible,
            'products' => $query->orderBy('id')->paginate(20)->withQueryString(),
            'counts' => [
                'Produits' => Product::count(),
                'Visibles sur le site' => $catalogueVisible ? Product::where('published', true)->count() : 0,
                'Disponibles' => Product::where('details->available', true)->count(),
            ],
        ]);
    }

    public function create() { return view('staff.product', ['product' => new Product(['details' => []])]); }
    public function edit(Product $product) { return view('staff.product', compact('product')); }

    public function catalogueVisibility(Request $request)
    {
        $request->validate(['visible' => 'required|boolean']);
        DB::transaction(function () use ($request) {
            $setting = SiteSetting::updateOrCreate(['key' => 'catalogue_visible'], ['enabled' => $request->boolean('visible')]);
            Audit::record($setting->enabled ? 'catalogue_affiche' : 'catalogue_masque', $setting);
        });
        return redirect()->route('staff.products.index')->with('success', $request->boolean('visible')
            ? 'Catalogue affiché sur le site. Les visibilités individuelles des produits sont conservées.'
            : 'Catalogue masqué sur le site. Les produits restent disponibles dans l’espace pro.');
    }

    public function store(ProductRequest $request)
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->productData());
            \App\Services\MediaStorage::apply($product, $request);
            Audit::record('produit_cree', $product);
            return $product;
        });
        return redirect()->route('staff.products.edit', $product)->with('success', 'Produit et coûts enregistrés.');
    }

    public function update(ProductRequest $request, Product $product)
    {
        DB::transaction(function () use ($request, $product) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $locked->update($request->productData());
            \App\Services\MediaStorage::apply($locked, $request);
            Audit::record('produit_modifie', $locked);
        });
        return redirect()->route('staff.products.edit', $product)->with('success', 'Produit, disponibilité, visibilité et coûts enregistrés.');
    }

    public function visibility(Request $request, Product $product)
    {
        $request->validate(['published' => 'required|boolean', 'available' => 'required|boolean']);
        DB::transaction(function () use ($request, $product) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $details = $locked->details;
            $details['available'] = $request->boolean('available');
            $locked->update(['published' => $request->boolean('published'), 'details' => $details]);
            Audit::record('disponibilite_visibilite_modifiees', $locked);
        });
        return back()->with('success', 'Disponibilité et visibilité enregistrées.');
    }
}
