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
            'HTTP_X-Inertia' => 'true',
            'HTTP_X-Inertia-Version' => '1',
        ]);
    }

    // ─── Failing test: Inertia POST to create token should NOT return JSON ───

    public function test_inertia_create_token_with_valid_name_returns_redirect_not_json(): void
    {
        $this->makeUserUsingSetup();

        $response = $this->webPost('/api-tokens', ['name' => 'my-token']);

        $response->assertStatus(302);
        $this->assertFalse($response->headers->has('X-Inertia'));
    }

    public function test_inertia_create_token_with_empty_name_returns_redirect_not_json(): void
    {
        $this->makeUserUsingSetup();

        $response = $this->webPost('/api-tokens', ['name' => '']);

        $response->assertStatus(302);
        $this->assertFalse($response->headers->has('X-Inertia'));
    }

    public function test_inertia_create_token_succeeds_and_token_exists_in_db(): void
    {
        $this->makeUserUsingSetup();

        $response = $this->webPost('/api-tokens', ['name' => 'test-token']);

        $response->assertStatus(302);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'test-token']);
    }

    public function test_inertia_delete_token_succeeds(): void
    {
        $this->makeUserUsingSetup();

        $this->webPost('/api-tokens', ['name' => 'to-delete']);
        $token = \Laravel\Sanctum\PersonalAccessToken::first();

        $response = $this->delete("/api-tokens/{$token->id}", [], [
            'HTTP_X-Inertia' => 'true',
            'HTTP_X-Inertia-Version' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    // ─── Token display after creation ───

    public function test_token_page_shows_created_token_after_creation(): void
    {
        $this->makeUserUsingSetup();

        $this->webPost('/api-tokens', ['name' => 'display-token']);

        $response = $this->followingRedirects()->get('/admin-config');
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

        $response = $this->get('/admin-config');
        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('Admin/Settings')
                ->has('server_configs')
                ->has('api_sections')
        );
    }

    public function test_created_token_is_only_shown_once(): void
    {
        $this->makeUserUsingSetup();

        // Create a token - flash data is set in session via $moreInfo
        $this->webPost('/api-tokens', ['name' => 'once-only']);

        // First visit - follow redirect chain to Settings, token should be present in more_info
        $firstVisit = $this->followingRedirects()->get('/admin-config');
        $firstVisit->assertOk();
        $firstVisit->assertInertia(
            fn($page) => $page
                ->component('Admin/Settings')
                ->has('flash.more_info.plain_text_token')
        );

        // Second visit - flash data should be consumed, no token shown
        $secondVisit = $this->followingRedirects()->get('/admin-config');
        $secondVisit->assertOk();
        $secondVisit->assertInertia(
            fn($page) => $page
                ->component('Admin/Settings')
                ->where('flash.more_info', null)
        );
    }
}
