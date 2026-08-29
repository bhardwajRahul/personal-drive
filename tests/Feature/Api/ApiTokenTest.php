<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\BaseFeatureTest;

class ApiTokenTest extends BaseFeatureTest
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_token_endpoints(): void
    {
        $get = $this->getJson('/api/v1/tokens');
        $this->assertContains($get->status(), [401, 403]);

        $post = $this->postJson('/api/v1/tokens', ['name' => 'test']);
        $this->assertContains($post->status(), [401, 403]);

        $delete = $this->deleteJson('/api/v1/tokens/1');
        $this->assertContains($delete->status(), [401, 403]);
    }

    public function test_authenticated_user_can_list_tokens(): void
    {
        $user = $this->makeUser();

        $response = $this->getJson('/api/v1/tokens');

        $response->assertOk()
            ->assertJsonCount(0, 'tokens');
    }

    public function test_authenticated_user_can_create_token(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/v1/tokens', ['name' => 'my-api-token']);

        $response->assertOk()
            ->assertJsonStructure([
                'token' => ['id', 'name', 'created_at'],
                'plain_text_token',
            ])
            ->assertJsonPath('token.name', 'my-api-token');

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'my-api-token']);
    }

    public function test_token_name_is_required(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/tokens', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_authenticated_user_can_delete_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('to-delete', ['api']);

        $response = $this->deleteJson('/api/v1/tokens/' . $token->accessToken->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'to-delete']);
    }

    public function test_delete_nonexistent_token_returns_404(): void
    {
        $this->makeUser();

        $response = $this->deleteJson('/api/v1/tokens/999');

        $response->assertNotFound();
    }

    public function test_created_token_works_for_api_auth(): void
    {
        $user = $this->makeUser();

        // Create a sanctum-protected test endpoint
        Route::middleware(['auth:sanctum'])->get('/api/test-protected', fn () => response()->json(['user' => auth()->id()]));

        // Create token via management endpoint
        $createResponse = $this->postJson('/api/v1/tokens', ['name' => 'test-api']);
        $createResponse->assertOk();
        $plainToken = $createResponse->json('plain_text_token');

        // Use token for auth
        $response = $this->getJson('/api/test-protected', [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('user', $user->id);
    }

    public function test_invalid_token_is_rejected(): void
    {
        Route::middleware(['auth:sanctum'])->get('/api/test-protected-invalid', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/api/test-protected-invalid', [
            'Authorization' => 'Bearer invalid-token-string',
        ]);

        $response->assertUnauthorized();
    }

    public function test_disable_auth_does_not_bypass_token_requirements(): void
    {
        config(['personal_drive.disable_auth' => true]);

        $response = $this->getJson('/api/v1/tokens');

        $this->assertContains($response->status(), [401, 403]);
    }
}
