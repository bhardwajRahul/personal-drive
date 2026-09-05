<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRequest;
use App\Models\LocalFile;
use App\Traits\HasJsonPagination;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    use HasJsonPagination;

    public function __invoke(SearchRequest $request): JsonResponse
    {
        $query = $request->validated('q');
        $perPage = $request->validated('per_page', 50);

        $paginator = LocalFile::searchFiles($query)
            ->paginate($perPage);

        return $this->paginateJson($paginator, 'files');
    }
}
