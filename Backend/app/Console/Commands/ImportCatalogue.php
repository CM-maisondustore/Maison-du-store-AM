<?php
namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Importe le relevé électroménager et meubles dans `products`.
 * Idempotent, ne change jamais la publication ni les coûts d'un produit existant ; un nouveau produit reste masqué.
 * Les prix relevés sont des références, souvent promotionnelles : ils ne sont ni repris comme prix de vente ni exposés sur le site.
 */
class ImportCatalogue extends Command
{
    protected $signature = 'catalogue:import {--dir= : Dossier contenant electromenager.json et meubles.json}';
    protected $description = 'Importe le relevé électroménager et meubles, sans publier aucun produit.';

    public function handle(): int
    {
        $dir = rtrim($this->option('dir') ?: database_path('data/catalogue'), '/\\');
        $stats = ['created' => 0, 'updated' => 0];
        foreach (['electromenager', 'meubles'] as $file) {
            $path = $dir.'/'.$file.'.json';
            if (!is_file($path)) { $this->error('Fichier introuvable : '.$path); return self::FAILURE; }
            foreach (json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['produits'] as $row) {
                $reference = mb_strtoupper(mb_substr($row['id'], 0, 50));
                $universe = $row['univers'] === 'electromenager' ? 'electromenager' : (($row['categorie'] ?? '') === 'bureau' ? 'meubles_bureau' : 'meubles_domicile');
                $product = Product::firstOrNew(['reference' => $reference]);
                $details = $product->details ?? ['available' => false, 'featured' => false, 'features' => [], 'colors' => [], 'specs' => [], 'price' => 'Prix'];
                $specs = [];
                foreach ($row['caracteristiques'] ?? [] as $label => $value) {
                    if (is_bool($value)) $value = $value ? 'oui' : 'non';
                    if (is_array($value)) $value = implode(', ', $value);
                    if ($value !== null && $value !== '') $specs[] = ['label' => ucfirst(str_replace('_', ' ', $label)), 'value' => (string) $value];
                }
                $details = [...$details, 'name' => mb_substr($row['nom'], 0, 150), 'type' => $row['univers'] === 'electromenager' ? 'electromenager' : 'meuble', 'universe' => $universe,
                    'category' => $row['categorie_libelle'] ?? $row['categorie'] ?? '', 'description' => trim(($row['type'] ?? '').' '.($row['marque'] ?? '').' '.($row['modele'] ?? '')),
                    'quoteModel' => $row['type'] ?? '', 'specs' => $specs, 'reference_price' => $row['prix_fcfa'] ?? null, 'reference_source' => ($row['source'] ?? '').' '.($row['releve_le'] ?? '')];
                $exists = $product->exists;
                $product->details = $details;
                if (!$exists) $product->published = false;
                $product->save();
                $stats[$exists ? 'updated' : 'created']++;
            }
        }
        $this->info("Catalogue importé : {$stats['created']} créé(s), {$stats['updated']} mis à jour. Aucun produit n’a été publié.");
        return self::SUCCESS;
    }
}
