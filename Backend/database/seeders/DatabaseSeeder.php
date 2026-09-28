<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CommunicationSeeder::class);
        $this->call(SiteContentSeeder::class);
        $products = json_decode(file_get_contents(database_path('data/products.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($products as $product) {
            $reference = $product['reference'];
            unset($product['id'], $product['reference']);
            // Un nouveau seed ne remplace pas les modifications du catalogue en base.
            Product::firstOrCreate(['reference' => $reference], ['details' => $product]);
        }
        $this->call(QuoteOptionSeeder::class);
    }
}
