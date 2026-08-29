<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    // ─── Token Lifecycle ───

    public function test_duplicate_token_names_are_allowed(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/tokens', ['name' => 'my-token'])->assertOk();
        $this->postJson('/api/v1/tokens', ['name' => 'my-token'])->assertOk();

        $response = $this->getJson('/api/v1/tokens');
        $response->assertOk()->assertJsonCount(2, 'tokens');
    }

    public function test_token_abilities_are_set_to_api(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/tokens', ['name' => 'ability-test'])->assertOk();

        $token = DB::table('personal_access_tokens')->where('name', 'ability-test')->first();
        $this->assertNotNull($token);
        $this->assertEquals('["api"]', $token->abilities);
    }

    public function test_plain_text_token_not_retrievable_after_creation(): void
    {
        $user = $this->makeUser();

        $createResponse = $this->postJson('/api/v1/tokens', ['name' => 'secret-token']);
        $createResponse->assertOk();

        $listResponse = $this->getJson('/api/v1/tokens');
        $tokens = $listResponse->json('tokens');

        foreach ($tokens as $token) {
            $this->assertArrayNotHasKey('token', $token);
            $this->assertArrayNotHasKey('plain_text_token', $token);
        }
    }

    public function test_token_last_used_at_updates_after_api_call(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('used-token', ['api']);
        $plainTextToken = $token->plainTextToken;
        $tokenId = $token->accessToken->id;

        // Verify last_used_at is initially null
        $dbBefore = DB::table('personal_access_tokens')->where('id', $tokenId)->first();
        $this->assertNull($dbBefore->last_used_at);

        // Clear session so sanctum guard must use the bearer token
        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        // Use the token to hit a sanctum-protected endpoint
        Route::middleware(['auth:sanctum'])->get('/api/test-used2', fn () => response()->json(['ok' => true]));
        $this->getJson('/api/test-used2', [
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->assertOk();

        // Verify last_used_at is set in the database (Sanctum guard updates it)
        $dbAfter = DB::table('personal_access_tokens')->where('id', $tokenId)->first();
        $this->assertNotNull($dbAfter->last_used_at);
    }

    public function test_user_sees_only_own_tokens(): void
    {
        $userA = $this->makeUser();
        $this->postJson('/api/v1/tokens', ['name' => 'token-a'])->assertOk();

        // Create user B and their token
        $userB = User::create([
            'username' => 'userb',
            'is_admin' => false,
            'password' => 'password',
        ]);
        $userB->createToken('token-b', ['api']);

        // User A lists tokens — should only see their own
        $this->actingAs($userA);
        $response = $this->getJson('/api/v1/tokens');
        $response->assertOk();

        $names = array_column($response->json('tokens'), 'name');
        $this->assertContains('token-a', $names);
        $this->assertNotContains('token-b', $names);
    }

    public function test_user_cannot_delete_other_users_token(): void
    {
        $userA = $this->makeUser();
        $tokenB = User::create([
            'username' => 'userb',
            'is_admin' => false,
            'password' => 'password',
        ])->createToken('token-b', ['api']);

        $response = $this->deleteJson('/api/v1/tokens/' . $tokenB->accessToken->id);
        $response->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'token-b']);
    }

    public function test_token_name_max_length(): void
    {
        $user = $this->makeUser();

        // 255 chars — should succeed
        $name255 = str_repeat('a', 255);
        $this->postJson('/api/v1/tokens', ['name' => $name255])->assertOk();

        // 256 chars — should fail
        $name256 = str_repeat('a', 256);
        $this->postJson('/api/v1/tokens', ['name' => $name256])->assertStatus(422);
    }

    public function test_bearer_token_malformed_header_returns_401(): void
    {
        $response = $this->getJson('/api/v1/files', [
            'Authorization' => 'Bearer garbage-token-value',
        ]);

        $response->assertUnauthorized();
    }

    public function test_bearer_token_missing_prefix_returns_401(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test', ['api'])->plainTextToken;

        // Send token without 'Bearer ' prefix — session auth may override in tests,
        // so verify the token string is NOT accepted as-is by checking the auth guard
        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        $response = $this->getJson('/api/v1/files', [
            'Authorization' => $token,
        ]);

        // Without session auth and without proper Bearer prefix, should be 401
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_empty_bearer_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/files', [
            'Authorization' => 'Bearer ',
        ]);

        $response->assertUnauthorized();
    }

    public function test_token_list_does_not_expose_plain_text_hash(): void
    {
        $user = $this->makeUser();
        $this->postJson('/api/v1/tokens', ['name' => 'check-list'])->assertOk();

        $response = $this->getJson('/api/v1/tokens');
        $tokens = $response->json('tokens');

        foreach ($tokens as $tokenData) {
            $this->assertArrayNotHasKey('token', $tokenData);
            $this->assertArrayNotHasKey('plain_text_token', $tokenData);
            $this->assertArrayNotHasKey('hash', $tokenData);
        }
    }

    public function test_create_token_returns_consistent_id_format(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/v1/tokens', ['name' => 'id-format-test']);
        $response->assertOk();

        $tokenId = $response->json('token.id');
        $this->assertIsNumeric($tokenId);
        $this->assertGreaterThan(0, $tokenId);
    }
}
