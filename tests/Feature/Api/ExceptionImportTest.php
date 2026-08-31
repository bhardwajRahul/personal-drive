<?php

namespace Tests\Feature\Api;

use Tests\Feature\BaseFeatureTest;

class ExceptionImportTest extends BaseFeatureTest
{
    public function test_api_404_returns_json_not_500(): void
    {
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();

        $token = \App\Models\User::first()
            ->createToken('test', ['api'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->get('/api/v1/_test/missing');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Not found']);
    }

    public function test_api_403_returns_json_not_500(): void
    {
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();

        $token = \App\Models\User::first()
            ->createToken('test', ['api'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->get('/api/v1/_test/forbidden');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden']);
    }
}
