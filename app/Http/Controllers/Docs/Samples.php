<?php

namespace App\Http\Controllers\Docs;

use OpenApi\Annotations as OA;

/**
 * Additional OpenAPI docs for sample routes defined as closures in routes/api.php.
 * These are documented here (instead of inline) so Swagger can include them.
 */
class Samples
{
    /**
     * Unsecured widgets sample
     *
     * @OA\Get(
     *   path="/api/v1/widgets/{id}",
     *   summary="Get widget (sample, unsecured)",
     *   tags={"samples"},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="id", type="string"),
     *       @OA\Property(property="name", type="string")
     *     )
     *   )
     * )
     */
    public function widgets(): void {}

    /**
     * Secured widgets sample
     *
     * @OA\Get(
     *   path="/api/v1/secure/widgets/{id}",
     *   summary="Get widget (sample, secured)",
     *   tags={"samples"},
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="id", type="string"),
     *       @OA\Property(property="name", type="string")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function widgetsSecure(): void {}

    /**
     * Unsecured validation sample
     *
     * @OA\Post(
     *   path="/api/v1/test/validation",
     *   summary="Validation example (unsecured)",
     *   tags={"samples"},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       required={"email"},
     *       @OA\Property(property="email", type="string", format="email")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function validation(): void {}

    /**
     * Secured validation sample
     *
     * @OA\Post(
     *   path="/api/v1/secure/test/validation",
     *   summary="Validation example (secured)",
     *   tags={"samples"},
     *   security={{"bearerAuth": {}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       required={"email"},
     *       @OA\Property(property="email", type="string", format="email")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function validationSecure(): void {}
}

