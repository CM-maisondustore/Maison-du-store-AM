<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuoteOption extends Model
{
    protected $guarded=['id'];
    protected $casts=['product_ids'=>'array','active'=>'boolean'];
    public function compatible(string $category, int $product, bool $motorized): bool
    {
        return $this->active && $this->category===$category && (!$this->product_ids || in_array($product,$this->product_ids,true))
            && ($this->motorization==='any' || $this->motorization===($motorized ? 'yes' : 'no'));
    }
}
