<?php

namespace App\OpenApi\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     required={"id","name","email"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="Test User"),
 *     @OA\Property(property="email", type="string", format="email", example="test@example.com"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, example="2025-01-01T12:00:00Z"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="UserCreate",
 *     type="object",
 *     required={"name","email","password"},
 *     @OA\Property(property="name", type="string", example="Ada Lovelace"),
 *     @OA\Property(property="email", type="string", format="email", example="ada@example.com"),
 *     @OA\Property(property="password", type="string", format="password", example="secret123")
 * )
 *
 * @OA\Schema(
 *     schema="UserUpdate",
 *     type="object",
 *     @OA\Property(property="name", type="string", example="Ada L."),
 *     @OA\Property(property="email", type="string", format="email", example="ada.l@example.com"),
 *     @OA\Property(property="password", type="string", format="password", example="newSecret123")
 * )
 *
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", example="Not Found")
 * )
 */
class UserSchemas {}

