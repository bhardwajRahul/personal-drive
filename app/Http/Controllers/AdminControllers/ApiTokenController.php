<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ApiTokenController extends Controller
{
    public function showPage()
    {
        $user = Auth::user();
        $tokens = $user->tokens()
            ->select('id', 'name', 'created_at', 'last_used_at', 'abilities')
            ->get();

        return inertia('Admin/ApiTokens', ['tokens' => $tokens]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate(['name' => 'required|string|max:255']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $token = $request->user()->createToken($request->name, ['api']);

        return redirect()->back()->with([
            'plain_text_token' => $token->plainTextToken,
            'token_name' => $token->accessToken->name,
            'token_id' => $token->accessToken->id,
        ]);
    }

    public function destroy(Request $request, string $tokenId)
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return redirect()->back()->withErrors(['token' => 'Token not found']);
        }

        return redirect()->back()->with('message', 'Token deleted');
    }
}
