<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Support\ApiResponse;
use App\Repositories\Contracts\ProductRepositoryInterface;

/**
 * @OA\Get(
 *   path="/api/v1/products",
 *   operationId="listProducts",
 *   tags={"Products"},
 *   summary="List products",
 *   @OA\Parameter(
 *     name="limit",
 *     in="query",
 *     required=false,
 *     description="Max number of products to return",
 *     @OA\Schema(type="integer", default=50, minimum=1, maximum=200)
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="List of products",
 *     @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Product"))
 *   )
 * )
 *
 * @OA\Get(
 *   path="/api/v1/products/error-demo",
 *   operationId="productErrorDemo",
 *   tags={"Products"},
 *   summary="Trigger a demo error and log it (try/catch)",
 *   @OA\Response(response=500, description="Demo error intentionally thrown and logged")
 * )
 *
 * @OA\Get(
 *   path="/api/v1/products/error-uncaught",
 *   operationId="productErrorUncaught",
 *   tags={"Products"},
 *   summary="Trigger an uncaught exception to see default Laravel error logging",
 *   @OA\Response(response=500, description="Uncaught exception")
 * )
 *
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
    public function __construct(private ProductRepositoryInterface $products) {}

    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(200, $limit));
        $items = $this->products->getAll($limit);
        return ApiResponse::success($items);
    }

    public function show(string $sku)
    {
        $product = $this->products->findBySku($sku);
        if (!$product) {
            return ApiResponse::error('NOT_FOUND', 'Product not found', null, 404);
        }

        return ApiResponse::success($product);
    }

    public function errorDemo()
    {
        try {
            // Intentionally trigger an error
            throw new \RuntimeException('Demo exception from ProductController::errorDemo');
        } catch (\Throwable $e) {
            Log::error('product.error_demo', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return ApiResponse::error('DEMO_ERROR', 'A demo error was thrown and logged', null, 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required', 'string'],
            'name' => ['required', 'string'],
            'price' => ['nullable', 'numeric'],
        ]);

        $created = $this->products->create($data);
        return ApiResponse::success($created, null, 201);
    }

    public function errorUncaught()
    {
        // Throw without try/catch so Laravel’s exception handler logs it
        throw new \RuntimeException('Uncaught demo exception from ProductController::errorUncaught');
    }
}
