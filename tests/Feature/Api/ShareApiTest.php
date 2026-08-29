<?php

namespace Tests\Feature\Api;

use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BaseFeatureTest;

class ShareApiTest extends BaseFeatureTest
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

    private function createTestFiles(int $count = 2): array
    {
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $this->uploadFile('', "share-file-{$i}.txt", 100);
            $files[] = LocalFile::where('filename', "share-file-{$i}.txt")->first();
        }
        return $files;
    }

    public function test_list_shares(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'list-share-test',
            'expiry' => 13,
        ], $this->authHeaders());

        $response = $this->getJson('/api/v1/shares', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'shares')
            ->assertJsonPath('shares.0.slug', 'list-share-test');
    }

    public function test_create_share(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'my-share',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('share.slug', 'my-share')
            ->assertJsonStructure(['share', 'url']);

        $this->assertDatabaseHas('shares', ['slug' => 'my-share']);
    }

    public function test_create_share_with_password(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'protected-share',
            'password' => 'secret123',
        ], $this->authHeaders());

        $response->assertOk();

        $share = Share::where('slug', 'protected-share')->first();
        $this->assertNotEmpty($share->password);
        $this->assertNotEquals('secret123', $share->password);
    }

    public function test_delete_share(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'delete-me',
        ], $this->authHeaders());

        $share = Share::where('slug', 'delete-me')->first();

        $response = $this->deleteJson("/api/v1/shares/{$share->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Share deleted');

        $this->assertDatabaseMissing('shares', ['id' => $share->id]);
    }

    public function test_toggle_share(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'toggle-share',
            'expiry' => 13,
        ], $this->authHeaders());

        $share = Share::where('slug', 'toggle-share')->first();
        $this->assertTrue((bool) $share->enabled);

        $response = $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Share paused');

        $share->refresh();
        $this->assertFalse((bool) $share->enabled);

        $response = $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Share enabled');

        $share->refresh();
        $this->assertTrue((bool) $share->enabled);
    }

    public function test_toggle_nonexistent_share_returns_404(): void
    {
        $response = $this->postJson('/api/v1/shares/99999/toggle', [], $this->authHeaders());

        $response->assertNotFound()
            ->assertJsonPath('message', 'Share not found');
    }

    public function test_shares_requires_auth(): void
    {
        $this->forceLogout();

        $response = $this->getJson('/api/v1/shares');

        $this->assertContains($response->status(), [401, 403]);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
