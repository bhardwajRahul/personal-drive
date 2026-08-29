<?php

namespace Tests\Feature\Api;

use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\BaseFeatureTest;

class ApiPolishTest extends BaseFeatureTest
{
    use RefreshDatabase;

    private User $apiUser;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();

        $this->apiUser = User::first();
        $this->token = $this->apiUser->createToken('test-api-token', ['api'])->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function forceLogout(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();
    }

    // ─── Pagination Structure ───

    public function test_list_files_returns_pagination_structure(): void
    {
        $response = $this->getJson('/api/v1/files', $this->authHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'files',
                'path',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_list_favorites_returns_pagination_structure(): void
    {
        $response = $this->getJson('/api/v1/favorites', $this->authHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'favorites',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_list_shares_returns_pagination_structure(): void
    {
        $response = $this->getJson('/api/v1/shares', $this->authHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'shares',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_search_returns_pagination_structure(): void
    {
        $response = $this->getJson('/api/v1/search?q=test', $this->authHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'files',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_per_page_can_be_customized(): void
    {
        $this->uploadFile('', 'page-test.txt', 100);

        $response = $this->getJson('/api/v1/files?per_page=5', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_per_page_max_is_100(): void
    {
        $response = $this->getJson('/api/v1/files?per_page=200', $this->authHeaders());

        $response->assertStatus(422);
    }

    // ─── Error Responses ───

    public function test_unauthenticated_api_returns_json_401(): void
    {
        $this->forceLogout();

        $response = $this->getJson('/api/v1/files');

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_not_found_api_returns_json_404(): void
    {
        $response = $this->getJson('/api/v1/files/' . \Illuminate\Support\Str::ulid(), $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_validation_error_returns_json_422(): void
    {
        $response = $this->postJson('/api/v1/files/create', [], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ─── Rate Limiting ───

    public function test_api_rate_limiter_is_configured(): void
    {
        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('api');

        $this->assertNotNull($limiter, 'API rate limiter should be configured');
    }

    public function test_api_routes_have_rate_limit_headers(): void
    {
        $response = $this->getJson('/api/v1/files', $this->authHeaders());

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    // ─── CORS ───

    public function test_cors_headers_present_for_options(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/files', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:82',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:82');
        $response->assertHeader('Access-Control-Allow-Methods');
        $response->assertHeader('Access-Control-Allow-Headers');
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
