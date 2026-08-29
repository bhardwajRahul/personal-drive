<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListFavoritesRequest;
use App\Http\Requests\Api\StoreFavoriteRequest;
use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FavoriteController extends Controller
{
    public function index(ListFavoritesRequest $request): JsonResponse
    {
        $perPage = $request->validated('per_page', 50);

        $paginator = Favorite::with('localFile:id,filename,public_path,is_dir')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('favorited_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'favorites' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $user = $request->user();
        $localFileIds = array_values(array_unique($request->validated('local_file_ids')));

        $localFiles = LocalFile::where('user_id', $user->id)
            ->whereIn('id', $localFileIds)
            ->get();

        if ($localFiles->count() !== count($localFileIds)) {
            return response()->json([
                'message' => 'One or more files do not belong to you.',
            ], 422);
        }

        $favoritedAt = now();
        Favorite::upsert(
            $localFiles->map(fn (LocalFile $localFile) => [
                'user_id' => $user->id,
                'local_file_id' => $localFile->id,
                'favorited_at' => $favoritedAt,
            ])->all(),
            ['user_id', 'local_file_id'],
            ['updated_at']
        );

        return response()->json(['favorites' => $this->favoritesFor($user)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        Favorite::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Favorite removed']);
    }

    private function favoritesFor(User $user): Collection
    {
        return Favorite::with('localFile:id,filename,public_path,is_dir')
            ->where('user_id', $user->id)
            ->orderByDesc('favorited_at')
            ->orderByDesc('id')
            ->get();
    }
}
