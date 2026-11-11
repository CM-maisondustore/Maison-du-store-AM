<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class QuoteOption extends Model
{
    use SoftDeletes;
    protected $guarded=['id'];
    public const KINDS = ['material'=>'Tissu / matériau','finish'=>'Finition','support'=>'Rail / barre','tape'=>'Reflète','hooks'=>'Accroches','motor'=>'Motorisation','sewing'=>'Confection','installation'=>'Installation','delivery'=>'Livraison','transport'=>'Transport','travel'=>'Déplacement','combined'=>'Pose et livraison'];
    public const UNITS = ['m'=>'Mètre linéaire','m2'=>'Mètre carré','piece'=>'Pièce','lot100'=>'Lot de 100 pièces','opening'=>'Ouverture','flat'=>'Forfait'];
    protected $casts=['product_ids'=>'array','active'=>'boolean','finish_codes'=>'array','unit_price'=>'integer','public_choice'=>'boolean'];

    /*
     * Chiffrage proposé quand l'option est choisie dans un devis (null = réglage historique du formulaire).
     * Modes : manual = quantité saisie / quantité par défaut ; coefficient = largeur × coefficient si quantité vide ;
     * per_meter / fixed = confection au mètre ou au forfait. Base : métrage du tissu principal ou des deux tissus.
     */
    public const CALC_MODES = [
        'material' => ['manual' => 'Métrage manuel', 'coefficient' => 'Largeur × coefficient'],
        'support' => ['manual' => 'Quantité manuelle / largeur', 'coefficient' => 'Largeur × coefficient'],
        'tape' => ['manual' => 'Quantité manuelle / métrage du tissu', 'coefficient' => 'Largeur × coefficient'],
        'sewing' => ['per_meter' => 'Par mètre de tissu', 'fixed' => 'Au forfait'],
    ];
    public const CALC_COEFFICIENT_KINDS = ['material', 'support', 'tape'];
    public const CALC_BASIS_KINDS = ['support', 'tape', 'sewing'];
    public const CALC_BASES = ['primary' => 'Tissu principal', 'all_fabrics' => 'Tissu principal + voilage'];
    public const CALC_DEFAULT_COEFFICIENT = ['material' => '2.5', 'support' => '1', 'tape' => '1'];

    /** Coefficient sans zéros inutiles (2.500 → 2.5), null si non configuré. */
    public function calcCoefficient(): ?string
    {
        return $this->calc_coefficient === null ? null : rtrim(rtrim((string) $this->calc_coefficient, '0'), '.');
    }
    public function media() { return $this->belongsTo(MediaAsset::class,'media_asset_id'); }
    public function compatible(string $category, int $product, bool $motorized): bool
    {
        return $this->active && $this->category===$category && ($product===0 || !$this->product_ids || in_array($product,$this->product_ids,true))
            && ($this->motorization==='any' || $this->motorization===($motorized ? 'yes' : 'no'));
    }
}
