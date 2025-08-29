<?php

namespace App\Http\Controllers\Docs;

use OpenApi\Annotations as OA;

/**
 * Defines the reusable Bearer (JWT) security scheme for Swagger UI.
 * L5-Swagger scans the app/ directory by default (see config/l5-swagger.php).
 *
 * After generation, Swagger UI will show an Authorize button where
 * you can paste a JWT in the format: `Bearer <token>`.
 */
class Security
{
    /**
     * @OA\SecurityScheme(
     *     securityScheme="bearerAuth",
     *     type="http",
     *     scheme="bearer",
     *     bearerFormat="JWT",
     *     description="JWT Bearer token. Example: Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
     * )
     */
    public function docs(): void
    {
        // Intentionally empty: used only for OpenAPI annotations.
    }
}

