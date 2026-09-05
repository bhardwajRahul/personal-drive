<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApiTokenRequest;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    use FlashMessages;

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken($request->validated('name'), ['api']);

        return $this->success('Token created', [
            'plain_text_token' => $token->plainTextToken,
            'token_name' => $token->accessToken->name,
        ]);
    }

    public function destroy(Request $request, string $tokenId): RedirectResponse
    {
        if (!$request->user()->deleteToken($tokenId)) {
            return $this->error('Token not found');
        }

        return $this->success('Token deleted');
    }
}
