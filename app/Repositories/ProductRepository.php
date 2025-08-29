<?php

namespace App\Repositories;

use App\Models\Product;

class ProductRepository
{
    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)->first();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }
}

