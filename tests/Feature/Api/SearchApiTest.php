<?php

namespace Tests\Feature\Api;

use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BaseFeatureTest;

class SearchApiTest extends BaseFeatureTest
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

    public function test_search_returns_matching_files(): void
    {
        $this->uploadFile('', 'invoice.pdf', 100);
        $this->uploadFile('', 'photo.jpg', 100);

        $response = $this->getJson('/api/v1/search?q=invoice', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.filename', 'invoice.pdf');
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        $this->uploadFile('', 'invoice.pdf', 100);

        $response = $this->getJson('/api/v1/search?q=nonexistent', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(0, 'files');
    }

    public function test_search_only_returns_own_files(): void
    {
        // Upload as current user
        $this->uploadFile('', 'my-file.txt', 100);

        // Create another user and upload their file
        $otherUser = User::create([
            'username' => 'othersearch',
            'is_admin' => false,
            'password' => 'password',
        ]);

        // Insert a file directly for the other user
        LocalFile::create([
            'filename' => 'other-file.txt',
            'public_path' => '',
            'private_path' => Storage::disk('local')->path(CONTENT_SUBDIR),
            'user_id' => $otherUser->id,
            'size' => 100,
            'is_dir' => false,
            'file_type' => 'text',
        ]);

        $response = $this->getJson('/api/v1/search?q=file', $this->authHeaders());

        $response->assertOk();

        $filenames = collect($response->json('files'))->pluck('filename')->toArray();
        $this->assertContains('my-file.txt', $filenames);
        $this->assertNotContains('other-file.txt', $filenames);
    }

    public function test_search_requires_auth(): void
    {
        $this->forceLogout();

        $response = $this->getJson('/api/v1/search?q=test');

        $this->assertContains($response->status(), [401, 403]);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
