<?php

namespace Tests;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    protected function seedProducts(): void
    {
        $this->seed();
        foreach (json_decode(file_get_contents(database_path('data/products.json')),true) as $details) {
            $reference=$details['reference']; unset($details['id'],$details['reference']);
            \App\Models\Product::firstOrCreate(['reference'=>$reference],['details'=>$details]);
        }
    }
}
