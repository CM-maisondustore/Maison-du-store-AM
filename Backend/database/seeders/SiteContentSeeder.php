<?php
namespace Database\Seeders;
use App\Models\SiteContent;
use App\Services\MediaStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $hero = SiteContent::firstOrCreate(['slot' => 'hero'], ['kind' => 'hero', 'title' => 'Stores & Rideaux Sur Mesure', 'description' => 'L’élégance à votre fenêtre : confectionnés à Abidjan avec les plus beaux tissus et matières nobles pour sublimer durablement votre intérieur.', 'image_alt' => 'Façade du showroom Maison du Store AM avec rideaux et stores', 'published' => true]);
            if ($hero->wasRecentlyCreated) { $hero->media_asset_id = MediaStorage::fromBytes(file_get_contents(database_path('data/hero.webp')))->id; $hero->save(); }
            SiteContent::firstOrCreate(['slot' => 'contact'], ['kind' => 'contact', 'title' => 'Illustration du showroom', 'published' => true]);
            foreach ([
                ['Appartement Haussmannien', 'Voilages légers et rideaux tamisants en lin naturel.'],
                ['Villa Contemporaine', 'Stores californiens motorisés pour d’immenses baies vitrées.'],
                ['Chambre d’Hôtel Particulier', 'Rideaux occultants à doublure thermique et double tombé fluide.'],
                ['Bureau d’Anthracite', 'Stores intérieurs occultants gris anthracite tout en sobriété.'],
            ] as $index => [$title, $description]) {
                SiteContent::firstOrCreate(['slot' => 'portfolio-initial-'.$index], ['kind' => 'portfolio', 'title' => $title, 'description' => $description, 'position' => $index, 'published' => true]);
            }
        });
    }
}
