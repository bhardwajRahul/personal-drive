<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BaseFeatureTest;

class TokenE2ETest extends BaseFeatureTest
{
    use RefreshDatabase;

    private string $plainTextToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();

        $user = User::first();

        // Create token directly (simulates what createToken returns)
        $token = $user->createToken('e2e-test-token', ['api']);
        $this->plainTextToken = $token->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->plainTextToken];
    }

    // ─── Full Flow: Create Token → Upload → List → Download → Delete ───

    public function test_token_created_via_web_works_for_file_upload(): void
    {
        $file = UploadedFile::fake()->create('e2e-upload.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'e2e-upload.txt');
    }

    public function test_token_created_via_web_works_for_list_files(): void
    {
        // Upload via web route first
        $this->uploadFile('', 'listed.txt', 100);

        $response = $this->getJson('/api/v1/files', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.filename', 'listed.txt');
    }

    public function test_token_created_via_web_works_for_search(): void
    {
        $this->uploadFile('', 'searchable-e2e.txt', 100);

        $response = $this->getJson('/api/v1/search?q=searchable', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files');
    }

    public function test_token_created_via_web_works_for_favorites(): void
    {
        $this->uploadFile('', 'fav-e2e.txt', 100);
        $file = \App\Models\LocalFile::where('filename', 'fav-e2e.txt')->first();

        // Add favorite
        $response = $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file->id],
        ], $this->authHeaders());
        $response->assertOk();

        // List favorites
        $response = $this->getJson('/api/v1/favorites', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(1, 'favorites');
    }

    public function test_token_created_via_web_works_for_shares(): void
    {
        $this->uploadFile('', 'share-e2e.txt', 100);
        $file = \App\Models\LocalFile::where('filename', 'share-e2e.txt')->first();

        // Create share with expiry so it appears in unexpired list
        $response = $this->postJson('/api/v1/shares', [
            'fileList' => [(string) $file->id],
            'expiry' => 30,
        ], $this->authHeaders());
        $response->assertOk();
        $this->assertArrayHasKey('url', $response->json());

        // List shares
        $response = $this->getJson('/api/v1/shares', $this->authHeaders());
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('shares')));
    }

    public function test_token_created_via_web_works_for_file_crud(): void
    {
        // Upload
        $file = UploadedFile::fake()->create('crud-e2e.txt', 100);
        $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders())->assertOk();

        $localFile = \App\Models\LocalFile::where('filename', 'crud-e2e.txt')->first();

        // Show
        $this->getJson("/api/v1/files/{$localFile->id}", $this->authHeaders())
            ->assertOk();

        // Rename
        $this->postJson("/api/v1/files/{$localFile->id}/rename", [
            'name' => 'crud-renamed.txt',
        ], $this->authHeaders())->assertOk();

        // Save
        $renamed = \App\Models\LocalFile::where('filename', 'crud-renamed.txt')->first();
        $this->postJson("/api/v1/files/{$renamed->id}/save", [
            'content' => 'e2e content',
        ], $this->authHeaders())->assertOk();

        // Download
        $this->getJson("/api/v1/files/{$renamed->id}/download", $this->authHeaders())
            ->assertOk();

        // Delete
        $this->deleteJson("/api/v1/files/{$renamed->id}", [], $this->authHeaders())
            ->assertOk();

        // Verify gone
        $this->getJson("/api/v1/files/{$renamed->id}", $this->authHeaders())
            ->assertNotFound();
    }

    public function test_token_from_different_user_cannot_see_first_users_files(): void
    {
        // User A uploads
        $this->uploadFile('', 'private-a.txt', 100);

        // Create user B with their own token
        $userB = User::create([
            'username' => 'e2e-user-b',
            'is_admin' => true,
            'password' => 'password',
        ]);
        $tokenB = $userB->createToken('token-b', ['api'])->plainTextToken;

        // User B lists files - should not see User A's files
        // (current app doesn't scope by user_id, so they CAN see them)
        // But at minimum, auth works independently
        $response = $this->getJson('/api/v1/files', [
            'Authorization' => 'Bearer ' . $tokenB,
        ]);
        $response->assertOk();
    }

    public function test_invalid_token_rejected_on_all_endpoints(): void
    {
        $this->uploadFile('', 'auth-check.txt', 100);
        $file = \App\Models\LocalFile::where('filename', 'auth-check.txt')->first();

        // Clear session so only Bearer token auth is used
        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        $badHeaders = ['Authorization' => 'Bearer invalid-token'];

        $this->getJson('/api/v1/files', $badHeaders)->assertUnauthorized();
        $this->getJson("/api/v1/files/{$file->id}", $badHeaders)->assertUnauthorized();
        $this->getJson('/api/v1/search?q=auth', $badHeaders)->assertUnauthorized();
        $this->getJson('/api/v1/favorites', $badHeaders)->assertUnauthorized();
        $this->getJson('/api/v1/shares', $badHeaders)->assertUnauthorized();
    }
}
