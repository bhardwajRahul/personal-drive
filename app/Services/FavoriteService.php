<?php

namespace App\Services;

use App\Models\Favorite;
use App\Models\LocalFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FavoriteService
{
    public function list(): Collection
    {
        return Favorite::getForUserQuery(auth()->user()->id)->get();
    }

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Favorite::getForUserQuery(auth()->user()->id)->paginate($perPage);
    }

    /**
     * @param array<string> $localFileIds
     * @return array{success: bool, message: string, favorites?: Collection}
     */
    public function store(array $localFileIds): array
    {
        $localFileIds = array_values(array_unique($localFileIds));
        $userId = auth()->user()->id;

        $localFiles = LocalFile::where('user_id', $userId)
            ->whereIn('id', $localFileIds)
            ->get();

        if ($localFiles->count() !== count($localFileIds)) {
            return ['success' => false, 'message' => 'One or more files do not belong to you.'];
        }

        $this->upsertFavorites($userId, $localFiles);

        return ['success' => true, 'message' => 'Favorites updated', 'favorites' => $this->list()];
    }

    public function remove(string $favoriteId): void
    {
        Favorite::where('id', $favoriteId)
            ->where('user_id', auth()->user()->id)
            ->delete();
    }

    private function upsertFavorites(int $userId, Collection $localFiles): void
    {
        $rows = [];
        $now = now();
        foreach ($localFiles as $localFile) {
            $rows[] = [
                'user_id' => $userId,
                'local_file_id' => $localFile->id,
                'favorited_at' => $now,
            ];
        }

        Favorite::upsert($rows, ['user_id', 'local_file_id'], ['favorited_at', 'updated_at']);
    }
}
