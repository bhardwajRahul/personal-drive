<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApiTokenRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenController extends Controller
{
    public function store(StoreApiTokenRequest $request)
    {
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
