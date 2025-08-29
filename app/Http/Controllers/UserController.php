<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// Swagger/OpenAPI annotations
// L5-Swagger scans docblocks in the app/ and routes/ directories per config/l5-swagger.php
// Use OpenApi\Annotations as OA;
use OpenApi\Annotations as OA;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * @OA\Tag(
     *     name="users",
     *     description="User management endpoints"
     * )
     */
    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     path="/api/v1/users",
     *     tags={"users"},
     *     summary="List users",
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(type="array", items=@OA\Items(ref="#/components/schemas/User"))
     *     )
     * )
     */
    /**
     * Secure variant: requires Bearer token via bearerAuth scheme.
     *
     * @OA\Get(
     *     path="/api/v1/secure/users",
     *     tags={"users"},
     *     summary="List users (secured)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(type="array", items=@OA\Items(ref="#/components/schemas/User"))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index()
    {
        $users = User::query()->orderBy('id')->get();
        return response()->json($users, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/v1/users",
     *     tags={"users"},
     *     summary="Create user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UserCreate")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/Error"))
     * )
     */
    /**
     * Secure variant: requires Bearer token via bearerAuth scheme.
     *
     * @OA\Post(
     *     path="/api/v1/secure/users",
     *     tags={"users"},
     *     summary="Create user (secured)",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UserCreate")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/Error"))
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = new User();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     tags={"users"},
     *     summary="Get user by ID",
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer", format="int64")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/User")),
     *     @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/Error"))
     * )
     */
    /**
     * Secure variant: requires Bearer token via bearerAuth scheme.
     *
     * @OA\Get(
     *     path="/api/v1/secure/users/{id}",
     *     tags={"users"},
     *     summary="Get user by ID (secured)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer", format="int64")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/User")),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/Error"))
     * )
     */
    public function show(string $id)
    {
        $user = User::query()->findOrFail((int) $id);
        return response()->json($user, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     tags={"users"},
     *     summary="Update user",
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer", format="int64")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UserUpdate")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/User")),
     *     @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/Error")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/Error"))
     * )
     */
    /**
     * Secure variant: requires Bearer token via bearerAuth scheme.
     *
     * @OA\Put(
     *     path="/api/v1/secure/users/{id}",
     *     tags={"users"},
     *     summary="Update user (secured)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer", format="int64")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UserUpdate")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/User")),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/Error")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/Error"))
     * )
     */
    public function update(Request $request, string $id)
    {
        $user = User::query()->findOrFail((int) $id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8'],
        ]);

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }
        if (array_key_exists('password', $validated)) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        return response()->json($user, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     tags={"users"},
     *     summary="Delete user",
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer", format="int64")),
     *     @OA\Response(response=204, description="No Content"),
     *     @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/Error"))
     * )
     */
    /**
     * Secure variant: requires Bearer token via bearerAuth scheme.
     *
     * @OA\Delete(
     *     path="/api/v1/secure/users/{id}",
     *     tags={"users"},
     *     summary="Delete user (secured)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer", format="int64")),
     *     @OA\Response(response=204, description="No Content"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/Error"))
     * )
     */
    public function destroy(string $id)
    {
        $user = User::query()->findOrFail((int) $id);
        $user->delete();
        return response()->noContent();
    }
}
