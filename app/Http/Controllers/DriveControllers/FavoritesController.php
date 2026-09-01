<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\StoreFavoritesRequest;
use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FavoritesController extends Controller
{
    public function __construct(
        private FavoriteService $favoriteService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['favorites' => $this->favoriteService->list()]);
    }

    public function store(StoreFavoritesRequest $request): JsonResponse
    {
        $result = $this->favoriteService->store($request->validated('local_file_ids'));

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'errors' => ['local_file_ids' => [$result['message']]],
            ], 422);
        }

        return response()->json(['favorites' => $result['favorites']]);
    }

    public function destroy(string $favoriteId): Response
    {
        $this->favoriteService->remove($favoriteId);
        return response()->noContent();
    }
}
