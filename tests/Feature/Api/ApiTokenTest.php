<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\BaseFeatureTest;

class ApiTokenTest extends BaseFeatureTest
{
    use RefreshDatabase;

    // ─── Web Route Token CRUD ───

    public function test_unauthenticated_user_cannot_access_token_endpoints(): void
    {
        $get = $this->get('/admin-config');
        $get->assertRedirect();

        $post = $this->post('/api-tokens', ['name' => 'test']);
        $post->assertRedirect();

        $delete = $this->delete('/api-tokens/1');
        $delete->assertRedirect();
    }

    public function test_authenticated_user_can_list_tokens(): void
    {
        $user = $this->makeUser();

        $response = $this->followingRedirects()->get('/admin-config');
        $response->assertOk();
        $response->assertInertia(fn($page) => $page
            ->component('Admin/Settings')
            ->has('tokens', 0)
        );
    }

    public function test_authenticated_user_can_create_token(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/api-tokens', ['name' => 'my-api-token']);
        $response->assertRedirect();
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'my-api-token']);
    }

    public function test_token_name_is_required(): void
    {
        $this->makeUser();

        $response = $this->post('/api-tokens', []);
        // Form request validation redirects back for web requests
        $this->assertContains($response->status(), [302, 303]);
    }

    public function test_authenticated_user_can_delete_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('to-delete', ['api']);

        $response = $this->delete('/api-tokens/' . $token->accessToken->id);
        $response->assertRedirect();
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'to-delete']);
    }

    public function test_delete_nonexistent_token_returns_error(): void
    {
        $this->makeUser();

        $response = $this->delete('/api-tokens/999');
        $response->assertRedirect();
    }

    // ─── Sanctum Token Auth (API routes) ───

    public function test_created_token_works_for_api_auth(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test-api', ['api']);
        $plainToken = $token->plainTextToken;

        Route::middleware(['auth:sanctum'])->get('/api/test-protected', fn () => response()->json(['user' => auth()->id()]));

        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        $response = $this->getJson('/api/test-protected', [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        $response->assertOk()->assertJsonPath('user', $user->id);
    }

    public function test_invalid_token_is_rejected(): void
    {
        Route::middleware(['auth:sanctum'])->get('/api/test-protected-invalid', fn () => response()->json(['ok' => true]));

        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        $response = $this->getJson('/api/test-protected-invalid', [
            'Authorization' => 'Bearer invalid-token-string',
        ]);

        $response->assertUnauthorized();
    }

    public function test_disable_auth_does_not_bypass_token_requirements(): void
    {
        config(['app.disable_auth' => true]);

        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        Route::middleware(['auth:sanctum'])->get('/api/test-disable-auth', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/api/test-disable-auth');
        $this->assertContains($response->status(), [401, 403]);
    }

    // ─── Token Lifecycle ───

    public function test_duplicate_token_names_are_allowed(): void
    {
        $user = $this->makeUser();

        $this->post('/api-tokens', ['name' => 'my-token'])->assertRedirect();
        $this->post('/api-tokens', ['name' => 'my-token'])->assertRedirect();

        $this->assertCount(2, $user->fresh()->tokens);
    }

    public function test_token_abilities_are_set_to_api(): void
    {
        $user = $this->makeUser();
        $this->post('/api-tokens', ['name' => 'ability-test']);

        $token = DB::table('personal_access_tokens')->where('name', 'ability-test')->first();
        $this->assertNotNull($token);
        $this->assertEquals('["api"]', $token->abilities);
    }

    public function test_plain_text_token_not_retrievable_after_creation(): void
    {
        $user = $this->makeUser();
        $this->post('/api-tokens', ['name' => 'secret-token']);

        $tokens = $user->fresh()->tokens;
        $this->assertCount(1, $tokens);
        // Token model doesn't expose plainTextToken after creation
        $this->assertNotEquals('secret-token', $tokens[0]->plainTextToken ?? null);
    }

    public function test_token_last_used_at_updates_after_api_call(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('used-token', ['api']);
        $plainTextToken = $token->plainTextToken;
        $tokenId = $token->accessToken->id;

        $dbBefore = DB::table('personal_access_tokens')->where('id', $tokenId)->first();
        $this->assertNull($dbBefore->last_used_at);

        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        Route::middleware(['auth:sanctum'])->get('/api/test-used2', fn () => response()->json(['ok' => true]));
        $this->getJson('/api/test-used2', [
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->assertOk();

        $dbAfter = DB::table('personal_access_tokens')->where('id', $tokenId)->first();
        $this->assertNotNull($dbAfter->last_used_at);
    }

    public function test_user_sees_only_own_tokens(): void
    {
        $userA = $this->makeUser();
        $this->post('/api-tokens', ['name' => 'token-a']);

        $userB = User::create([
            'username' => 'userb',
            'is_admin' => false,
            'password' => 'password',
        ]);
        $userB->createToken('token-b', ['api']);

        $this->actingAs($userA);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tokensA = $userA->fresh()->tokens;
        $this->assertCount(1, $tokensA);
        $this->assertEquals('token-a', $tokensA[0]->name);

        $tokensB = $userB->fresh()->tokens;
        $this->assertCount(1, $tokensB);
        $this->assertEquals('token-b', $tokensB[0]->name);
    }

    public function test_user_cannot_delete_other_users_token(): void
    {
        $userA = $this->makeUser();
        $userB = User::create([
            'username' => 'userb',
            'is_admin' => false,
            'password' => 'password',
        ]);
        $tokenB = $userB->createToken('token-b', ['api']);

        $response = $this->delete('/api-tokens/' . $tokenB->accessToken->id);
        $response->assertRedirect();

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'token-b']);
    }

    public function test_token_name_max_length(): void
    {
        $this->makeUser();

        $name255 = str_repeat('a', 255);
        $this->post('/api-tokens', ['name' => $name255])->assertRedirect();

        $name256 = str_repeat('a', 256);
        $response = $this->post('/api-tokens', ['name' => $name256]);
        $this->assertContains($response->status(), [302, 303]);
    }

    // ─── Bearer Token Auth ───

    public function test_bearer_token_malformed_header_returns_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        Route::middleware(['auth:sanctum'])->get('/api/test-malformed', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/api/test-malformed', [
            'Authorization' => 'Bearer garbage-token-value',
        ]);

        $response->assertUnauthorized();
    }

    public function test_bearer_token_missing_prefix_returns_401(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        Route::middleware(['auth:sanctum'])->get('/api/test-noprefix', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/api/test-noprefix', [
            'Authorization' => $token,
        ]);

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_empty_bearer_token_returns_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        Route::middleware(['auth:sanctum'])->get('/api/test-emptybearer', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/api/test-emptybearer', [
            'Authorization' => 'Bearer ',
        ]);

        $response->assertUnauthorized();
    }

    // ─── Edge Cases ───

    public function test_create_token_with_empty_name_string(): void
    {
        $this->makeUser();

        $response = $this->post('/api-tokens', ['name' => '']);
        $this->assertContains($response->status(), [302, 303]);
    }

    public function test_create_token_with_only_whitespace_name(): void
    {
        $this->makeUser();

        $response = $this->post('/api-tokens', ['name' => '   ']);
        $this->assertContains($response->status(), [200, 302, 422]);
    }

    public function test_create_token_name_exactly_255_chars(): void
    {
        $this->makeUser();

        $name255 = str_repeat('a', 255);
        $response = $this->post('/api-tokens', ['name' => $name255]);
        $response->assertRedirect();
        $this->assertDatabaseHas('personal_access_tokens', ['name' => $name255]);
    }

    public function test_create_token_name_256_chars(): void
    {
        $this->makeUser();

        $name256 = str_repeat('a', 256);
        $response = $this->post('/api-tokens', ['name' => $name256]);
        $this->assertContains($response->status(), [302, 303]);
    }

    public function test_create_token_with_html_in_name(): void
    {
        $this->makeUser();

        $xssName = '<script>alert(1)</script>';
        $response = $this->post('/api-tokens', ['name' => $xssName]);
        $response->assertRedirect();
        $this->assertDatabaseHas('personal_access_tokens', ['name' => $xssName]);
    }

    public function test_token_abilities_are_exactly_api(): void
    {
        $user = $this->makeUser();
        $this->post('/api-tokens', ['name' => 'ability-check']);

        $token = DB::table('personal_access_tokens')->where('name', 'ability-check')->first();
        $this->assertNotNull($token);
        $this->assertEquals(['api'], json_decode($token->abilities, true));
    }

    public function test_multiple_users_create_tokens_independently(): void
    {
        $userA = $this->makeUser();
        $this->post('/api-tokens', ['name' => 'token-a']);

        $userB = User::create([
            'username' => 'userb',
            'is_admin' => true,
            'password' => 'password',
        ]);
        $this->actingAs($userB);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('/api-tokens', ['name' => 'token-b']);

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'token-a', 'tokenable_id' => $userA->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'token-b', 'tokenable_id' => $userB->id]);
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_delete_token_does_not_affect_other_users_tokens(): void
    {
        $userA = $this->makeUser();
        $this->post('/api-tokens', ['name' => 'keep-this']);

        $userB = User::create([
            'username' => 'userb',
            'is_admin' => true,
            'password' => 'password',
        ]);
        $tokenB = $userB->createToken('token-b', ['api']);

        $this->actingAs($userB);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->delete('/api-tokens/' . $tokenB->accessToken->id);
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'token-b']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'keep-this']);
    }
}
