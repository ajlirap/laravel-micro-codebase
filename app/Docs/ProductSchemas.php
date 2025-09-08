<?php

namespace App\Docs;

/**
 * @OA\Tag(
 *   name="Products",
 *   description="Operations related to products"
 * )
 *
 * @OA\Schema(
 *   schema="Product",
 *   type="object",
 *   required={"sku","name"},
 *   @OA\Property(property="id", type="integer", example=123),
 *   @OA\Property(property="sku", type="string", example="SKU-001"),
 *   @OA\Property(property="name", type="string", example="Sample Product"),
 *   @OA\Property(property="price", type="number", format="float", example=19.99)
 * )
 */
class ProductSchemas
{
    // This class only holds OpenAPI annotations for scanning.
}

