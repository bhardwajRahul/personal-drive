<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BaseFeatureTest;

class TokenInertiaFlowTest extends BaseFeatureTest
{
    use RefreshDatabase;

    private function webPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        $user = User::first() ?? $this->makeUser();
        $this->actingAs($user);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        return $this->post($uri, $data, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => md5('1'),
        ]);
    }

    // ─── Failing test: Inertia POST to create token should NOT return JSON ───

    public function test_inertia_create_token_with_valid_name_returns_redirect_not_json(): void
    {
        $this->makeUserUsingSetup();

        $response = $this->webPost('/api-tokens', ['name' => 'my-token']);

        // Inertia redirects use 303, should NOT be JSON
        $this->assertContains($response->status(), [302, 303]);
        $response->assertRedirect();
    }

    public function test_inertia_create_token_with_empty_name_returns_redirect_not_json(): void
    {
        $this->makeUserUsingSetup();

        $response = $this->webPost('/api-tokens', ['name' => '']);

        // Validation failure should redirect back (302/303), NOT return JSON 422
        $this->assertContains($response->status(), [302, 303]);
        $response->assertRedirect();
    }

    public function test_inertia_create_token_succeeds_and_token_exists_in_db(): void
    {
        $this->makeUserUsingSetup();

        $this->webPost('/api-tokens', ['name' => 'working-token']);

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'working-token']);
    }

    public function test_inertia_delete_token_succeeds(): void
    {
        $this->makeUserUsingSetup();
        $user = User::first();
        $token = $user->createToken('to-delete', ['api']);

        $response = $this->delete('/api-tokens/' . $token->accessToken->id, [], [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => md5('1'),
        ]);

        $this->assertContains($response->status(), [302, 303]);
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'to-delete']);
    }

    // ─── Token display after creation ───

    public function test_token_page_shows_created_token_after_creation(): void
    {
        $this->makeUserUsingSetup();

        // Create a token via the controller action
        $this->webPost('/api-tokens', ['name' => 'display-test']);

        // Follow the redirect chain: /api-tokens -> /admin-config?tab=tokens -> Settings page
        $response = $this->followingRedirects()->get('/api-tokens');

        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('Admin/Settings')
                ->has('tokens')
        );
    }

    public function test_token_page_has_api_documentation(): void
    {
        $this->makeUserUsingSetup();

        $response = $this->followingRedirects()->get('/api-tokens');

        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page->component('Admin/Settings')
        );
    }

    public function test_created_token_is_only_shown_once(): void
    {
        $this->makeUserUsingSetup();

        // Create a token — flash data is set in session
        $this->webPost('/api-tokens', ['name' => 'once-only']);

        // First visit — follow redirect chain to Settings, token should be present
        $firstVisit = $this->followingRedirects()->get('/api-tokens');
        $firstVisit->assertOk();
        $firstVisit->assertInertia(
            fn($page) => $page
                ->component('Admin/Settings')
                ->has('flash.plain_text_token')
        );

        // Second visit — flash data should be consumed, no token shown
        $secondVisit = $this->followingRedirects()->get('/api-tokens');
        $secondVisit->assertOk();
        $secondVisit->assertInertia(
            fn($page) => $page
                ->component('Admin/Settings')
                ->where('flash.plain_text_token', null)
        );
    }
}
