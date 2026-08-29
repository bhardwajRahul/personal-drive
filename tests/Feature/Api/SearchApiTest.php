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

    public function test_search_is_case_insensitive(): void
    {
        $this->uploadFile('', 'README.txt', 100);

        $response = $this->getJson('/api/v1/search?q=readme', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.filename', 'README.txt');
    }

    public function test_search_returns_empty_for_empty_query(): void
    {
        $this->uploadFile('', 'test-file.txt', 100);

        $response = $this->getJson('/api/v1/search?q=', $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_search_does_not_return_other_users_files(): void
    {
        $this->uploadFile('', 'my-exclusive-file.txt', 100);

        $otherUser = User::create([
            'username' => 'other-search-user',
            'is_admin' => false,
            'password' => 'password',
        ]);

        LocalFile::create([
            'filename' => 'other-exclusive-file.txt',
            'public_path' => '',
            'private_path' => Storage::disk('local')->path(CONTENT_SUBDIR),
            'user_id' => $otherUser->id,
            'size' => 100,
            'is_dir' => false,
            'file_type' => 'text',
        ]);

        // Search for "exclusive" — both files would match if scoping didn't work
        $response = $this->getJson('/api/v1/search?q=exclusive', $this->authHeaders());

        $response->assertOk();

        $filenames = collect($response->json('files'))->pluck('filename')->toArray();
        $this->assertCount(1, $filenames);
        $this->assertContains('my-exclusive-file.txt', $filenames);
        $this->assertNotContains('other-exclusive-file.txt', $filenames);

        // Verify the result belongs to current user
        $fileId = $response->json('files.0.id');
        $localFile = LocalFile::find($fileId);
        $this->assertEquals($this->apiUser->id, $localFile->user_id);
    }

    public function test_search_results_have_expected_structure(): void
    {
        $this->uploadFile('', 'structured.txt', 100);

        $response = $this->getJson('/api/v1/search?q=structured', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonStructure([
                'files' => [
                    [
                        'id',
                        'filename',
                        'public_path',
                    ],
                ],
            ]);
    }

    public function test_search_with_special_characters(): void
    {
        $this->uploadFile('', 'file (1).txt', 100);

        $response = $this->getJson('/api/v1/search?q=file%20(1)', $this->authHeaders());

        $response->assertOk();

        // Should not crash — either find it or return empty
        $this->assertIsArray($response->json('files'));
    }

    public function test_search_with_single_character_query(): void
    {
        $this->uploadFile('', 'apple.txt', 100);

        $response = $this->getJson('/api/v1/search?q=a', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.filename', 'apple.txt');
    }

    public function test_search_with_no_results_returns_empty_array(): void
    {
        $response = $this->getJson('/api/v1/search?q=zzzznotfound', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(0, 'files');
    }

    public function test_search_results_have_id_filename_public_path(): void
    {
        $this->uploadFile('', 'field-check.txt', 100);

        $response = $this->getJson('/api/v1/search?q=field-check', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files');

        $file = $response->json('files.0');
        $this->assertArrayHasKey('id', $file);
        $this->assertArrayHasKey('filename', $file);
        $this->assertArrayHasKey('public_path', $file);
    }

    public function test_search_query_is_case_insensitive(): void
    {
        $this->uploadFile('', 'README.txt', 100);

        $response = $this->getJson('/api/v1/search?q=readme', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.filename', 'README.txt');
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
