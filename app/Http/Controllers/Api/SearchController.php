<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRequest;
use App\Models\LocalFile;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $query = $request->validated('q');
        $userId = $request->user()->id;

        $files = LocalFile::searchFiles($query, $userId);

        return response()->json(['files' => $files]);
    }
}
