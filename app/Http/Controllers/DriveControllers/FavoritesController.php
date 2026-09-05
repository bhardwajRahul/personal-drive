<?php

namespace App\Http\Controllers\DriveControllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\StoreFavoritesRequest;
use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;

class FavoritesController extends Controller
{
    protected FavoriteService $favoriteService;

    public function __construct(
        FavoriteService $favoriteService,
    ) {
        $this->favoriteService = $favoriteService;
    }

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

    public function destroy(string $favoriteId): JsonResponse
    {
        $result = $this->favoriteService->remove($favoriteId);
        return ResponseHelper::json($result['message'], $result['success']);
    }
}
