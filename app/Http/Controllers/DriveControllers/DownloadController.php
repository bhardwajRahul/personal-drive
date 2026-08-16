<?php

namespace App\Http\Controllers\DriveControllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\DownloadRequest;
use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Services\PathService;
use App\Services\ShareAuthorizationService;
use App\Traits\GuestResourceAuthorize;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController extends Controller
{
    use GuestResourceAuthorize;

    protected PathService $pathService;

    protected DownloadService $downloadService;

    protected ShareAuthorizationService $shareAuthorizationService;

    public function __construct(
        PathService $pathService,
        DownloadService $downloadService,
        ShareAuthorizationService $shareAuthorizationService,
    ) {
        $this->pathService = $pathService;
        $this->downloadService = $downloadService;
        $this->shareAuthorizationService = $shareAuthorizationService;
    }

    public function index(DownloadRequest $request): BinaryFileResponse|JsonResponse
    {
        $fileKeyArray = $request->validated('fileList');
        $localFiles = LocalFile::getByIds($fileKeyArray)->get();
        if ($localFiles->isEmpty()) {
            return ResponseHelper::json('Could not find files to download', false);
        }
        if ($localFiles->contains(fn (LocalFile $file) => !file_exists($file->getPrivatePathNameForFile()))) {
            return ResponseHelper::json('One or more selected files are unavailable', false, 404);
        }

        if (Session::get('share_id') && !$this->guestVerified($fileKeyArray, $this->shareAuthorizationService)) {
            return ResponseHelper::json('Error: authorization issue', false);
        }

        return $this->downloadValidFiles($localFiles);
    }

    public function downloadValidFiles(Collection $localFiles): BinaryFileResponse|JsonResponse
    {
        try {
            ignore_user_abort(true);

            $downloadFilePath = $this->downloadService->generateDownloadPath($localFiles);
            if (!file_exists($downloadFilePath)) {
                return ResponseHelper::json('Perhaps trying to download empty dir ? ', false);
            }

            $response = Response::download(
                $downloadFilePath,
                basename($downloadFilePath),
                ['Content-Disposition' => 'attachment; filename="' . basename($downloadFilePath) . '"']
            );
            return $this->downloadService->isSingleFile($localFiles) ? $response : $response->deleteFileAfterSend();
        } catch (Exception $e) {
            Log::error('Failed to prepare download', ['exception' => $e]);

            return ResponseHelper::json('Could not prepare download', false);
        }
    }
}
