<?php

namespace App\Http\Requests;

use App\Rules\Phone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        // Le formulaire accepte les décimales françaises.
        $dimensions = $this->input('dimensions');
        if (is_array($dimensions)) {
            foreach ($dimensions as &$window) {
                if (!is_array($window)) continue;
                foreach (['width', 'height'] as $field) {
                    if (isset($window[$field]) && is_string($window[$field])) {
                        $window[$field] = str_replace(',', '.', $window[$field]);
                    }
                }
            }
            $this->merge(['dimensions' => $dimensions]);
        }
    }

    public function rules(): array
    {
        $store = $this->routeIs('quotes.stores');
        $choices = $store ? [
            'model' => ['enrouleur', 'venitien', 'californien', 'plisse', 'bateau'],
            'mount' => ['murale', 'plafond', 'encastree', 'inconnu'],
            'orderType' => ['installation', 'remplacement', 'renovation'],
            'control' => ['manuelle', 'motorisee', 'inconnu'],
            'finish' => ['voile', 'oxford', 'technique'],
        ] : [
            'model' => ['occultant', 'voilage', 'double', 'automatique'],
            'fabric' => ['voile-tulle', 'lin-sauvage', 'coton-satine', 'polyester-recycle', 'velours-double', 'metisse-lin-coton'],
            'pattern' => ['uni', 'rayures', 'floral', 'geometrique', 'texture'],
            'lining' => ['sans', 'thermique', 'occultante', 'acoustique'],
            'finish' => ['oeillets', 'pattes', 'plis-flamands', 'plis-simples', 'ruban-fronceur'],
            'hanging' => ['tringle', 'rail-chemin', 'rail-wave', 'sans'],
        ];
        $colors = ['blanc', 'ecru', 'beige', 'taupe', 'gris', 'anthracite'];
        $options=\App\Services\PublicQuoteChoices::options()->where('category',$store ? 'store' : 'rideau');
        foreach (($store ? ['finish'=>'material'] : ['fabric'=>'material','finish'=>'finish','hanging'=>'support']) as $field=>$kind) {
            $codes=$options->where('kind',$kind)->pluck('code')->all();
            $choices[$field]=array_values(array_unique([...$choices[$field],...$codes]));
        }
        $choices['color'] = $store ? $colors : [...$colors, 'bleu', 'vert', 'bordeaux'];
        $keys = array_keys($choices);
        if ($store) $keys[] = 'powerSource';
        $rules = [
            'contact' => ['required', 'array:civility,lastName,firstName,email,phone,address,postalCode,city,notes'],
            'contact.civility' => ['required', Rule::in(['m', 'mme'])],
            'contact.lastName' => ['required', 'string', 'min:2', 'max:60'],
            'contact.firstName' => ['required', 'string', 'min:2', 'max:60'],
            'contact.email' => ['required', 'email', 'max:254'],
            'contact.phone' => ['required', 'string', 'max:30', new Phone],
            'contact.address' => ['required', 'string', 'min:4', 'max:120'],
            'contact.city' => ['required', 'string', 'min:2', 'max:60'],
            'contact.postalCode' => ['nullable', 'string', 'min:2', 'max:12'],
            'contact.notes' => ['nullable', 'string', 'max:3000'],
            'product' => ['required', 'array:'.implode(',', $keys)],
            'dimensions' => ['required', 'array', 'min:1', 'max:8'],
            'dimensions.*' => ['required', 'array:id,label,width,height,quantity'],
            'dimensions.*.id' => ['sometimes', 'string', 'max:60', 'distinct'],
            'dimensions.*.label' => ['nullable', 'string', 'max:120'],
            'dimensions.*.width' => ['required', 'numeric', 'between:20,400'],
            'dimensions.*.height' => ['required', 'numeric', 'between:20,400'],
            'dimensions.*.quantity' => ['required', 'integer', 'between:1,20'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'acceptTerms' => ['required', 'accepted'],
            'submittedAt' => ['sometimes', 'date'],
        ];
        foreach ($choices as $field => $values) {
            $rules['product.'.$field] = ['required', Rule::in($values)];
        }
        if ($store) {
            $rules['product.powerSource'] = ['exclude_unless:product.control,motorisee', 'required', Rule::in(['filaire', 'batterie', 'solaire'])];
        }
        return $rules;
    }
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->routeIs('quotes.stores')) return;
            $options=\App\Services\PublicQuoteChoices::options();
            $finish=$options->firstWhere('code',$this->input('product.finish'));
            $support=$options->firstWhere('code',$this->input('product.hanging'));
            if ($support && (($support->finish_codes && !in_array($finish?->code,$support->finish_codes,true)) || ($finish && $support->motorization!=='any' && $finish->motorization!=='any' && $support->motorization!==$finish->motorization))) $validator->errors()->add('product.hanging','Le support ne correspond pas à la finition choisie.');
        }];
    }
}
