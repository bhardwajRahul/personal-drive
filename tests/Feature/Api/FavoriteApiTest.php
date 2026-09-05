<?php

namespace Tests\Feature\Api;

use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            ->assertJsonCount(1, 'favorites')
            ->assertJsonMissingPath('favorites.0.local_file_id')
            ->assertJsonPath('favorites.0.local_file.id', $file->id);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->apiUser->id,
            'local_file_id' => $file->id,
        ]);
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

    public function test_add_duplicate_favorite_is_idempotent(): void
    {
        $this->uploadFile('', 'dup-fav.txt', 100);
        $file = LocalFile::where('filename', 'dup-fav.txt')->first();

        $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file->id],
        ], $this->authHeaders());

        $firstFavoritedAt = Favorite::where('user_id', $this->apiUser->id)
            ->where('local_file_id', $file->id)
            ->firstOrFail()->favorited_at;

        $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file->id],
        ], $this->authHeaders());

        $favorite = Favorite::where('user_id', $this->apiUser->id)
            ->where('local_file_id', $file->id)
            ->firstOrFail();

        $this->assertSame(1, Favorite::where('user_id', $this->apiUser->id)
            ->where('local_file_id', $file->id)
            ->count());
        $this->assertNotNull($favorite->favorited_at);
        $this->assertTrue($favorite->favorited_at->equalTo($firstFavoritedAt));
    }

    public function test_remove_nonexistent_favorite_succeeds_silently(): void
    {
        $nonExistentId = \Illuminate\Support\Str::ulid();

        $response = $this->deleteJson("/api/v1/favorites/{$nonExistentId}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Favorite removed');
    }

    public function test_other_users_file_not_found_for_favorite(): void
    {
        $otherUser = User::create([
            'username' => 'fav-other-user',
            'is_admin' => false,
            'password' => 'password',
        ]);

        $otherFile = LocalFile::create([
            'filename' => 'not-mine-fav.txt',
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

        $response->assertOk();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->apiUser->id,
            'local_file_id' => $otherFile->id,
        ]);
    }

    public function test_favorite_list_order_newest_first(): void
    {
        $files = [];
        for ($i = 0; $i < 3; $i++) {
            $this->uploadFile('', "order-fav-{$i}.txt", 100);
            $files[] = LocalFile::where('filename', "order-fav-{$i}.txt")->first();
        }

        // Add favorites with different timestamps
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/favorites', [
                'local_file_ids' => [(string) $files[$i]->id],
            ], $this->authHeaders());

            // Update favorited_at to stagger timestamps
            Favorite::where('user_id', $this->apiUser->id)
                ->where('local_file_id', $files[$i]->id)
                ->update(['favorited_at' => now()->subMinutes(10 * (2 - $i))]);
        }

        $response = $this->getJson('/api/v1/favorites', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(3, 'favorites');

        // API returns Favorite objects - get local_file.id from each
        $returnedFileIds = collect($response->json('favorites'))->pluck('local_file.id')->toArray();
        $expectedFileIds = [
            $files[2]->id,  // most recent favorited_at
            $files[1]->id,
            $files[0]->id,  // oldest favorited_at
        ];

        $this->assertEquals($expectedFileIds, $returnedFileIds);
    }

    public function test_favorite_local_file_relation_includes_expected_fields(): void
    {
        $this->uploadFile('', 'rel-fav.txt', 100);
        $file = LocalFile::where('filename', 'rel-fav.txt')->first();

        $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file->id],
        ], $this->authHeaders());

        $response = $this->getJson('/api/v1/favorites', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'favorites')
            ->assertJsonStructure([
                'favorites' => [
                    [
                        'id',
                        'local_file' => [
                            'id',
                            'filename',
                            'public_path',
                            'is_dir',
                        ],
                    ],
                ],
            ]);
    }

    public function test_add_favorite_with_empty_array_returns_422(): void
    {
        $response = $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [],
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_favorite_response_includes_local_file_relation(): void
    {
        $this->uploadFile('', 'rel-check-fav.txt', 100);
        $file = LocalFile::where('filename', 'rel-check-fav.txt')->first();

        $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file->id],
        ], $this->authHeaders());

        $response = $this->getJson('/api/v1/favorites', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'favorites');

        $favorite = $response->json('favorites.0');
        $this->assertArrayHasKey('local_file', $favorite);
        $this->assertArrayHasKey('filename', $favorite['local_file']);
        $this->assertEquals('rel-check-fav.txt', $favorite['local_file']['filename']);
    }

    public function test_add_multiple_favorites_at_once(): void
    {
        $this->uploadFile('', 'multi-fav-1.txt', 100);
        $this->uploadFile('', 'multi-fav-2.txt', 100);

        $file1 = LocalFile::where('filename', 'multi-fav-1.txt')->first();
        $file2 = LocalFile::where('filename', 'multi-fav-2.txt')->first();

        $response = $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file1->id, (string) $file2->id],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(2, 'favorites');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->apiUser->id,
            'local_file_id' => $file1->id,
        ]);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->apiUser->id,
            'local_file_id' => $file2->id,
        ]);
    }

    public function test_remove_favorite_does_not_delete_file(): void
    {
        $this->uploadFile('', 'fav-not-deleted.txt', 100);
        $file = LocalFile::where('filename', 'fav-not-deleted.txt')->first();

        // Add favorite
        $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file->id],
        ], $this->authHeaders());

        $favorite = Favorite::where('user_id', $this->apiUser->id)
            ->where('local_file_id', $file->id)
            ->first();

        // Remove favorite
        $this->deleteJson("/api/v1/favorites/{$favorite->id}", [], $this->authHeaders());

        // File should still exist in DB
        $this->assertDatabaseHas('local_files', ['id' => $file->id]);
        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    }

    // ─── New API Tests ───

    public function test_add_favorite_with_invalid_ulid_returns_422(): void
    {
        $response = $this->postJson('/api/v1/favorites', [
            'local_file_ids' => ['not-a-ulid'],
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_add_favorite_missing_local_file_ids_returns_422(): void
    {
        $response = $this->postJson('/api/v1/favorites', [], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_add_favorite_for_folder(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'fav-folder',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $folder = LocalFile::where('filename', 'fav-folder')->where('is_dir', true)->first();

        $response = $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $folder->id],
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertDatabaseHas('favorites', ['local_file_id' => $folder->id]);
    }

    public function test_favorite_list_per_page_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->uploadFile('', "fav-page-{$i}.txt", 100);
            $file = LocalFile::where('filename', "fav-page-{$i}.txt")->first();
            $this->postJson('/api/v1/favorites', [
                'local_file_ids' => [(string) $file->id],
            ], $this->authHeaders())->assertOk();
        }

        $response = $this->getJson('/api/v1/favorites?per_page=2', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(2, 'favorites')
            ->assertJsonPath('meta.total', 5);
    }

    public function test_favorites_across_users_isolated(): void
    {
        // User A favorites a file
        $this->uploadFile('', 'userA-fav.txt', 100);
        $file = LocalFile::where('filename', 'userA-fav.txt')->first();
        $this->postJson('/api/v1/favorites', [
            'local_file_ids' => [(string) $file->id],
        ], $this->authHeaders())->assertOk();

        // Create User B
        $userB = User::create(['username' => 'userB', 'is_admin' => false, 'password' => 'password']);
        $tokenB = $userB->createToken('b-token', ['api'])->plainTextToken;

        // Must flush session so EnsureFrontendRequestsAreStateful doesn't use User A's session
        $this->forceLogout();

        $response = $this->getJson('/api/v1/favorites', [
            'Authorization' => 'Bearer ' . $tokenB,
        ]);
        $response->assertOk()
            ->assertJsonCount(0, 'favorites');
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
