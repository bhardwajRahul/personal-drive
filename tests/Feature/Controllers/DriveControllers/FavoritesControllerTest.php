<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\BaseFeatureTest;

class FavoritesControllerTest extends BaseFeatureTest
{
    use RefreshDatabase;

    public function test_index_lists_only_current_user_favorites_newest_first(): void
    {
        $user = $this->makeUser();
        $olderFile = LocalFile::factory()->create(['user_id' => $user->id]);
        $newerFile = LocalFile::factory()->create(['user_id' => $user->id]);
        $olderFavorite = Favorite::factory()->create(
            [
                'user_id' => $user->id,
                'local_file_id' => $olderFile->id,
                'favorited_at' => now()->subMinute(),
            ]
        );
        $newerFavorite = Favorite::factory()->create(
            [
                'user_id' => $user->id,
                'local_file_id' => $newerFile->id,
                'favorited_at' => now(),
            ]
        );
        $otherUser = User::factory()->create();
        $otherFile = LocalFile::factory()->create(['user_id' => $otherUser->id]);
        Favorite::factory()->create(['user_id' => $otherUser->id, 'local_file_id' => $otherFile->id]);

        $response = $this->getJson(route('drive.favorites.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'favorites')
            ->assertJsonPath('favorites.0.id', $newerFavorite->id)
            ->assertJsonPath('favorites.0.local_file.id', $newerFile->id)
            ->assertJsonPath('favorites.0.local_file.filename', $newerFile->filename)
            ->assertJsonPath('favorites.1.id', $olderFavorite->id);
    }

    public function test_store_adds_owned_files_and_returns_favorites(): void
    {
        $user = $this->makeUser();
        $firstFile = LocalFile::factory()->create(['user_id' => $user->id]);
        $secondFile = LocalFile::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson(
            route('drive.favorites.store'),
            ['local_file_ids' => [$firstFile->id, $secondFile->id]]
        );

        $response->assertOk()->assertJsonCount(2, 'favorites');
        $this->assertDatabaseHas(
            'favorites',
            ['user_id' => $user->id, 'local_file_id' => $firstFile->id]
        );
        $this->assertDatabaseHas(
            'favorites',
            ['user_id' => $user->id, 'local_file_id' => $secondFile->id]
        );
    }

    public function test_store_is_idempotent_for_duplicate_requests(): void
    {
        $user = $this->makeUser();
        $localFile = LocalFile::factory()->create(['user_id' => $user->id]);
        $requestData = ['local_file_ids' => [$localFile->id, $localFile->id]];

        $this->postJson(route('drive.favorites.store'), $requestData)->assertOk();
        $this->postJson(route('drive.favorites.store'), $requestData)->assertOk();

        $this->assertSame(
            1,
            Favorite::where('user_id', $user->id)->where('local_file_id', $localFile->id)->count()
        );
    }

    public function test_store_rejects_files_owned_by_another_user(): void
    {
        $this->makeUser();
        $otherUser = User::factory()->create();
        $otherFile = LocalFile::factory()->create(['user_id' => $otherUser->id]);

        $this->postJson(
            route('drive.favorites.store'),
            ['local_file_ids' => [$otherFile->id]]
        )->assertUnprocessable()->assertJsonValidationErrors('local_file_ids');

        $this->assertDatabaseMissing('favorites', ['local_file_id' => $otherFile->id]);
    }

    public function test_destroy_is_idempotent_and_does_not_remove_another_users_favorite(): void
    {
        $user = $this->makeUser();
        $localFile = LocalFile::factory()->create(['user_id' => $user->id]);
        $favorite = Favorite::factory()->create(['user_id' => $user->id, 'local_file_id' => $localFile->id]);
        $otherUser = User::factory()->create();
        $otherFile = LocalFile::factory()->create(['user_id' => $otherUser->id]);
        $otherFavorite = Favorite::factory()->create(
            ['user_id' => $otherUser->id, 'local_file_id' => $otherFile->id]
        );

        $this->deleteJson(route('drive.favorites.destroy', ['favoriteId' => $favorite->id]))->assertNoContent();
        $this->deleteJson(route('drive.favorites.destroy', ['favoriteId' => $favorite->id]))->assertNoContent();
        $this->deleteJson(route('drive.favorites.destroy', ['favoriteId' => $otherFavorite->id]))->assertNoContent();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
        $this->assertDatabaseHas('favorites', ['id' => $otherFavorite->id]);
    }

    public function test_drive_page_includes_current_user_favorites_without_storage_scan(): void
    {
        $user = $this->makeUser();
        $localFile = LocalFile::factory()->create(['user_id' => $user->id]);
        $favorite = Favorite::factory()->create(['user_id' => $user->id, 'local_file_id' => $localFile->id]);

        $this->get(route('drive'))->assertInertia(
            fn (Assert $page) => $page
                ->has('favorites', 1)
                ->where('favorites.0.id', $favorite->id)
                ->where('favorites.0.local_file.id', $localFile->id)
                ->where('favorites.0.local_file.filename', $localFile->filename)
        );
    }

    public function test_favorite_endpoints_require_an_authenticated_owner(): void
    {
        User::factory()->create();
        $this->get(route('drive.favorites.index'))->assertRedirect(route('login'));
    }
}
