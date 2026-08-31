<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApiTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken($request->name, ['api']);

        return redirect()->back()->with(
            [
            'plain_text_token' => $token->plainTextToken,
            'token_name' => $token->accessToken->name,
            ]
        );
    }

    public function destroy(Request $request, string $tokenId): RedirectResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return redirect()->back()->withErrors(['token' => 'Token not found']);
        }

        return redirect()->back()->with('message', 'Token deleted');
    }
}
