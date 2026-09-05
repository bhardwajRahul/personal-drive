<?php

namespace Tests\Unit\Models;

use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorite_uses_a_ulid_and_records_favorited_time()
    {
        $favorite = Favorite::factory()->create();

        $this->assertTrue(Str::isUlid($favorite->id));
        $this->assertNotNull($favorite->refresh()->favorited_at);
    }

    public function test_favorite_belongs_to_its_user_and_local_file()
    {
        $user = User::factory()->create();
        $localFile = LocalFile::factory()->create(['user_id' => $user->id]);
        $favorite = Favorite::factory()->create(['user_id' => $user->id, 'local_file_id' => $localFile->id]);

        $this->assertTrue($favorite->user->is($user));
        $this->assertTrue($favorite->localFile->is($localFile));
        $this->assertTrue($user->favorites->contains($favorite));
        $this->assertTrue($localFile->favorites->contains($favorite));
    }

    public function test_user_cascade_deletes_favorites()
    {
        $favorite = Favorite::factory()->create();

        $favorite->user->delete();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    }

    public function test_local_file_cascade_deletes_favorites()
    {
        $favorite = Favorite::factory()->create();

        $favorite->localFile->delete();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    }

    public function test_user_cannot_favorite_a_local_file_twice()
    {
        $user = User::factory()->create();
        $localFile = LocalFile::factory()->create(['user_id' => $user->id]);
        Favorite::factory()->create(['user_id' => $user->id, 'local_file_id' => $localFile->id]);

        $this->expectException(QueryException::class);

        Favorite::factory()->create(['user_id' => $user->id, 'local_file_id' => $localFile->id]);
    }
}
