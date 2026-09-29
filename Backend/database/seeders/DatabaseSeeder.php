<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CommunicationSeeder::class);
        $this->call(SiteContentSeeder::class);
        $this->call(QuoteOptionSeeder::class);
        $this->call(QuoteTariffSeeder::class);
    }
}
