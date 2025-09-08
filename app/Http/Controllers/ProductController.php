<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\Contracts\ProductRepositoryInterface;

/**
 * @OA\Get(
 *   path="/api/v1/products/{sku}",
 *   operationId="getProductBySku",
 *   tags={"Products"},
 *   summary="Get a product by SKU",
 *   @OA\Parameter(
 *     name="sku",
 *     in="path",
 *     required=true,
 *     description="The product SKU",
 *     @OA\Schema(type="string")
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="Product found",
 *     @OA\JsonContent(ref="#/components/schemas/Product")
 *   ),
 *   @OA\Response(response=404, description="Product not found")
 * )
 *
 * @OA\Post(
 *   path="/api/v1/products",
 *   operationId="createProduct",
 *   tags={"Products"},
 *   summary="Create a product",
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       required={"sku","name"},
 *       @OA\Property(property="sku", type="string", example="SKU-001"),
 *       @OA\Property(property="name", type="string", example="Sample Product"),
 *       @OA\Property(property="price", type="number", format="float", example=19.99)
 *     )
 *   ),
 *   @OA\Response(
 *     response=201,
 *     description="Product created",
 *     @OA\JsonContent(ref="#/components/schemas/Product")
 *   ),
 *   @OA\Response(response=422, description="Validation error")
 * )
 */
class ProductController extends Controller
{
    public function __construct(private ProductRepositoryInterface $products)
    {
    }

    public function show(string $sku)
    {
        $product = $this->products->findBySku($sku);
        if (!$product) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Product not found']], 404);
        }

        return response()->json($product);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required', 'string'],
            'name' => ['required', 'string'],
            'price' => ['nullable', 'numeric'],
        ]);

        $created = $this->products->create($data);
        return response()->json($created, 201);
    }
}

