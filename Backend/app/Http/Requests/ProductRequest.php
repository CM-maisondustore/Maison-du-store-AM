<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

    public function rules(): array
    {
        $rules = [
            'reference' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($this->route('product'))],
            'name' => 'required|string|max:150', 'type' => 'required|in:store,rideau',
            'category' => 'required|string|max:150', 'description' => 'required|string|max:3000',
            'quoteModel' => 'required|string|max:150',
            'features' => 'nullable|array', 'features.*' => 'in:occultant,tamisant,motorise,decoratif',
            'colors' => 'nullable|array', 'colors.*' => 'in:blanc,ecru,beige,taupe,gris,anthracite,bleu,vert,bordeaux',
            'specs_text' => 'nullable|string|max:3000',
            'published' => 'required|boolean', 'available' => 'required|boolean', 'featured' => 'required|boolean',
        ];
        foreach (Product::COSTS as $cost => $label) {
            $rules[$cost.'_cost'] = ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'];
            $rules[$cost.'_cost_unit'] = ['required_with:'.$cost.'_cost', Rule::in(array_keys(Product::COST_UNITS))];
        }
        return $rules;
    }

    public function attributes(): array
    {
        $attributes = ['published' => 'visibilité sur le site', 'available' => 'disponibilité', 'featured' => 'mise en avant'];
        foreach (Product::COSTS as $cost => $label) {
            $attributes[$cost.'_cost'] = 'coût '.mb_strtolower($label);
            $attributes[$cost.'_cost_unit'] = 'unité du coût '.mb_strtolower($label);
        }
        return $attributes;
    }

    public function productData(): array
    {
        $data = $this->validated();
        $specs = [];
        foreach (preg_split('/\R/', $data['specs_text'] ?? '') as $line) {
            if (!str_contains($line, ':')) continue;
            [$label, $value] = explode(':', $line, 2);
            $specs[] = ['label' => trim($label), 'value' => trim($value)];
        }
        $costs = [];
        foreach (Product::COSTS as $cost => $label) {
            foreach ([$cost.'_cost', $cost.'_cost_unit'] as $field) {
                // Omitted fields from an older form must not erase saved costs.
                if (array_key_exists($field, $data)) $costs[$field] = $data[$field];
            }
        }
        return [
            ...$costs, 'reference' => $data['reference'], 'published' => $this->boolean('published'),
            'details' => [
                ...collect($data)->only(['name', 'type', 'category', 'description', 'quoteModel'])->all(),
                'features' => $data['features'] ?? [], 'colors' => $data['colors'] ?? [], 'specs' => $specs,
                'available' => $this->boolean('available'), 'featured' => $this->boolean('featured'), 'price' => 'Prix',
            ],
        ];
    }
}
