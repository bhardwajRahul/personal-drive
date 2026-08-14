<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\LocalFile;
use App\Services\PathService;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Requests\DriveRequests\FileManagerRequest;

class FileManagerController extends Controller
{
    public function index(FileManagerRequest $request, PathService $pathService): Response
    {
        $path = $request->validated('path') ?? '';

        $files = LocalFile::getFilesForPublicPath($path);

        return Inertia::render(
            'Drive/DriveHome',
            [
            'files' => $files,
            'favorites' => Favorite::with('localFile:id,filename,public_path,is_dir')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('favorited_at')
                ->orderByDesc('id')
                ->get(),
            'path' => '/drive' . ($path ? '/' . $path : ''),
            'token' => csrf_token(),
            'folderExists' => $path === '' || is_dir($pathService->genPrivatePathFromPublic($path)),
            ]
        );
    }
}
