<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\StoreFavoritesRequest;
use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class FavoritesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['favorites' => $this->favoritesFor($request->user())]);
    }

    public function store(StoreFavoritesRequest $request): JsonResponse
    {
        $user = $request->user();
        $localFileIds = array_values(array_unique($request->validated('local_file_ids')));
        $localFiles = LocalFile::where('user_id', $user->id)
            ->whereIn('id', $localFileIds)
            ->get();

        if ($localFiles->count() !== count($localFileIds)) {
            return response()->json(
                [
                    'message' => 'One or more files do not belong to you.',
                    'errors' => [
                        'local_file_ids' => ['One or more files do not belong to you.'],
                    ],
                ],
                422
            );
        }

        $favoritedAt = now();
        Favorite::upsert(
            $localFiles->map(
                fn (LocalFile $localFile) => [
                    'user_id' => $user->id,
                    'local_file_id' => $localFile->id,
                    'favorited_at' => $favoritedAt,
                ]
            )->all(),
            ['user_id', 'local_file_id'],
            ['updated_at']
        );

        return response()->json(['favorites' => $this->favoritesFor($user)]);
    }

    public function destroy(Request $request, string $favoriteId): Response
    {
        Favorite::where('id', $favoriteId)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->noContent();
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
