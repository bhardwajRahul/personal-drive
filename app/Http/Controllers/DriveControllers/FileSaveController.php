<?php

namespace App\Http\Controllers\DriveControllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\FileSaveRequest;
use App\Services\FileSaveService;
use Illuminate\Http\JsonResponse;

class FileSaveController extends Controller
{
    public function __construct(private FileSaveService $fileSaveService)
    {
    }

    public function update(FileSaveRequest $request): JsonResponse
    {
        $result = $this->fileSaveService->save(
            $request->validated('id'),
            $request->validated('content')
        );

        return ResponseHelper::json($result['message'], $result['success']);
    }
}
