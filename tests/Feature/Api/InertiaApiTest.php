<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\BaseFeatureTest;

class InertiaApiTest extends BaseFeatureTest
{
    use RefreshDatabase;

    // ── Token Page Inertia Rendering ──────────────────────────────────

    public function test_token_page_requires_authentication(): void
    {
        // Create an admin so setup isn't required (setup redirects to /setup/account)
        User::create([
            'username' => 'admin',
            'is_admin' => 1,
            'password' => 'password',
        ]);
        $this->logout();

        $response = $this->get('/api-tokens');
        $response->assertStatus(302);
        $response->assertRedirect();
    }

    public function test_token_page_renders_correct_inertia_component(): void
    {
        $this->makeUser();
        $response = $this->get('/api-tokens');
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->component('Admin/ApiTokens')
        );
    }

    public function test_token_page_passes_empty_tokens_when_none_exist(): void
    {
        $this->makeUser();
        $response = $this->get('/api-tokens');
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->has('tokens')
                ->count('tokens', 0)
        );
    }

    public function test_token_page_passes_existing_tokens(): void
    {
        $user = $this->makeUser();
        $user->createToken('token-one', ['api']);
        $user->createToken('token-two', ['api']);

        $response = $this->get('/api-tokens');
        $response->assertOk();
        // Use viewData to access Inertia page props
        $page = json_decode(json_encode($response->viewData('page')), true);
        $this->assertCount(2, $page['props']['tokens']);
    }

    public function test_token_page_tokens_contain_expected_fields(): void
    {
        $user = $this->makeUser();
        $user->createToken('my-token', ['api']);

        $response = $this->get('/api-tokens');
        $response->assertOk();
        $page = json_decode(json_encode($response->viewData('page')), true);
        $token = $page['props']['tokens'][0];

        $this->assertArrayHasKey('id', $token);
        $this->assertArrayHasKey('name', $token);
        $this->assertArrayHasKey('created_at', $token);
        $this->assertArrayHasKey('last_used_at', $token);
        $this->assertArrayHasKey('abilities', $token);
        $this->assertEquals('my-token', $token['name']);
    }

    public function test_token_page_does_not_expose_plain_text_token(): void
    {
        $user = $this->makeUser();
        $user->createToken('secret-token', ['api']);

        $response = $this->get('/api-tokens');
        $response->assertOk();
        $page = json_decode(json_encode($response->viewData('page')), true);

        // plain_text_token should NOT be in the Inertia props
        $this->assertArrayNotHasKey('plain_text_token', $page['props']);
        // Also check the tokens collection doesn't contain it
        foreach ($page['props']['tokens'] as $token) {
            $this->assertArrayNotHasKey('plain_text_token', $token);
        }
    }

    public function test_token_page_requires_admin_user(): void
    {
        $nonAdmin = User::create([
            'username' => 'regularuser',
            'is_admin' => 0,
            'password' => 'password',
        ]);
        $this->actingAs($nonAdmin);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $response = $this->get('/api-tokens');
        $response->assertStatus(302);
        $response->assertRedirect(route('rejected', ['message' => 'You do not have admin access']));
    }

    public function test_token_page_works_with_disable_auth(): void
    {
        config(['app.disable_auth' => true]);
        User::create([
            'username' => 'admin',
            'is_admin' => 1,
            'password' => 'password',
        ]);

        $response = $this->get('/api-tokens');
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->component('Admin/ApiTokens')
        );
    }

    // ── Token Web Routes (POST/DELETE through web, not API) ──────────

    public function test_create_token_via_web_route(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/api/v1/tokens', ['name' => 'web-created-token']);
        $response->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'web-created-token',
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_create_token_via_web_requires_name(): void
    {
        $this->makeUser();

        $response = $this->post('/api/v1/tokens', []);
        $response->assertStatus(422);
    }

    public function test_delete_token_via_web_route(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('to-delete', ['api']);

        $response = $this->delete('/api/v1/tokens/' . $token->accessToken->id);
        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'to-delete']);
    }

    public function test_delete_nonexistent_token_via_web_returns_404(): void
    {
        $this->makeUser();

        $response = $this->delete('/api/v1/tokens/99999');
        $response->assertNotFound();
    }
}
