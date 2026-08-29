<?php

namespace Tests\Feature\Api;

use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BaseFeatureTest;

class FavoriteApiTest extends BaseFeatureTest
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

    public function test_list_favorites_empty(): void
    {
        $response = $this->getJson('/api/v1/favorites', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(0, 'favorites');
    }

    public function test_add_favorite(): void
    {
        $this->uploadFile('', 'fav-me.txt', 100);
        $file = LocalFile::where('filename', 'fav-me.txt')->first();

        $response = $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file->id],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'favorites');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->apiUser->id,
            'local_file_id' => $file->id,
        ]);
    }

    public function test_add_favorite_validates_file_ownership(): void
    {
        // Create a file belonging to another user
        $otherUser = User::create([
            'username' => 'otherfav',
            'is_admin' => false,
            'password' => 'password',
        ]);

        $otherFile = LocalFile::create([
            'filename' => 'not-mine.txt',
            'public_path' => '',
            'private_path' => Storage::disk('local')->path(CONTENT_SUBDIR),
            'user_id' => $otherUser->id,
            'size' => 100,
            'is_dir' => false,
            'file_type' => 'text',
        ]);

        $response = $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $otherFile->id],
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('message', 'One or more files do not belong to you.');
    }

    public function test_remove_favorite(): void
    {
        $this->uploadFile('', 'remove-fav.txt', 100);
        $file = LocalFile::where('filename', 'remove-fav.txt')->first();

        // Add favorite first
        $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file->id],
        ], $this->authHeaders());

        $favorite = Favorite::where('user_id', $this->apiUser->id)
            ->where('local_file_id', $file->id)
            ->first();

        $response = $this->deleteJson("/api/v1/favorites/{$favorite->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Favorite removed');

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    }

    public function test_favorites_requires_auth(): void
    {
        $this->forceLogout();

        $response = $this->getJson('/api/v1/favorites');

        $this->assertContains($response->status(), [401, 403]);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
