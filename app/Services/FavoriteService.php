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
        return Favorite::listForCurrentUser()->get();
    }

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Favorite::listForCurrentUser()->paginate($perPage);
    }

    /**
     * @param array<string> $localFileIds
     * @return array{success: bool, message: string, favorites?: Collection}
     */
    public function store(array $localFileIds): array
    {
        $localFileIds = array_values(array_unique($localFileIds));

        $localFiles = LocalFile::getByIdsForUser($localFileIds)->get();

        $this->upsertFavorites($localFiles);

        return ['success' => true, 'message' => 'Favorites updated', 'favorites' => $this->list()];
    }

    public function remove(string $favoriteId): array
    {
        Favorite::removeForUser($favoriteId);

        return ['success' => true, 'message' => 'Favorite removed'];
    }

    private function upsertFavorites(Collection $localFiles): void
    {
        $rows = [];
        foreach ($localFiles as $localFile) {
            $rows[] = [
                'user_id' => auth()->user()->id,
                'local_file_id' => $localFile->id,
            ];
        }

        Favorite::upsert($rows, ['user_id', 'local_file_id'], ['updated_at']);
    }
}
