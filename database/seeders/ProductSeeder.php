<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::firstOrCreate(['sku' => 'EX-001'], ['name' => 'Example', 'price_cents' => 1999]);
    }
}

