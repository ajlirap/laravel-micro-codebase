<?php

namespace App\Repositories\Contracts;

use App\Models\Product;

interface ProductRepositoryInterface
{
    public function findBySku(string $sku): ?Product;

    public function create(array $data): Product;
}

