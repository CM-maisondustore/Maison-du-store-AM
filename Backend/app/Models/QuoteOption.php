<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class QuoteOption extends Model
{
    use SoftDeletes;
    protected $guarded=['id'];
    public const KINDS = ['material'=>'Tissu / matériau','finish'=>'Finition','support'=>'Rail / barre','tape'=>'Reflète','hooks'=>'Accroches','motor'=>'Motorisation','sewing'=>'Confection','installation'=>'Installation'];
    public const UNITS = ['m'=>'Mètre linéaire','m2'=>'Mètre carré','piece'=>'Pièce','lot100'=>'Lot de 100 pièces','opening'=>'Ouverture','flat'=>'Forfait'];
    protected $casts=['product_ids'=>'array','active'=>'boolean','finish_codes'=>'array','unit_price'=>'integer','public_choice'=>'boolean'];
    public function compatible(string $category, int $product, bool $motorized): bool
    {
        return $this->active && $this->category===$category && ($product===0 || !$this->product_ids || in_array($product,$this->product_ids,true))
            && ($this->motorization==='any' || $this->motorization===($motorized ? 'yes' : 'no'));
    }
}
