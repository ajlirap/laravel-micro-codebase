<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\ProductRepository;

class ProductRepositoryBindingTest extends TestCase
{
    public function test_default_binding_resolves_to_concrete(): void
    {
        $repo = app(ProductRepositoryInterface::class);
        $this->assertInstanceOf(ProductRepository::class, $repo);
    }

    public function test_interface_can_be_mocked_in_tests(): void
    {
        $mock = $this->createMock(ProductRepositoryInterface::class);
        app()->instance(ProductRepositoryInterface::class, $mock);

        $resolved = app(ProductRepositoryInterface::class);
        $this->assertSame($mock, $resolved);
    }
}

