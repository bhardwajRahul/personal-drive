<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ApiTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->select('id', 'name', 'created_at', 'last_used_at', 'abilities')
            ->get();

        return response()->json(['tokens' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate(['name' => 'required|string|max:255']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $token = $request->user()->createToken($request->name, ['api']);

        return response()->json([
            'token' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'created_at' => $token->accessToken->created_at,
            ],
            'plain_text_token' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, string $tokenId): Response|JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Token not found'], 404);
        }

        return response()->noContent();
    }
}
