<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    public function findBySku(string $sku): ?Product;

    public function create(array $data): Product;

    public function getAll(int $limit = 50): Collection;
}
