<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\CreateItemRequest;
use App\Http\Requests\DriveRequests\ReplaceAbortRequest;
use App\Http\Requests\DriveRequests\UploadRequest;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use App\Services\FileOperationsService;
use App\Services\UploadService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;


class UploadController extends Controller
{
    use FlashMessages;

    protected PathService $pathService;
    protected FileOperationsService $fileOperationsService;
    protected UploadService $uploadService;
    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        PathService $pathService,
        LocalFileStatsService $localFileStatsService,
        FileOperationsService $fileOperationsService,
        UploadService $uploadService,
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->pathService = $pathService;
        $this->fileOperationsService = $fileOperationsService;
        $this->uploadService = $uploadService;
    }

    public function store(UploadRequest $request): RedirectResponse
    {
        $files = $request->validated('files') ?? [];
        $publicPath = $request->validated('path') ?? '';
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->pathService->genPrivatePathFromPublic($publicPath);

        if (!$files) {
            return $this->error('File upload failed. No files uploaded');
        }
        if (!$privatePath) {
            return $this->error('File upload failed. Could not find storage path');
        }

        $result = $this->uploadService->processFileUpload($files, $privatePath, $publicPath, true);

        $conflictsMessage = '';
        if ($result['conflicts']) {
            $conflictsMessage = 'Conflicts: ' . implode(', ', array_slice($result['conflicts'], 0, 3))
                . ' cannot overwrite folders';
            $remaining = count($result['conflicts']) - 3;
            if ($remaining > 0) {
                $conflictsMessage .= ' (+' . $remaining . ' more)';
            }
        }

        if ($result['duplicates'] > 0) {
            session([
                'new_file_copied_num' => $result['successful'],
                'duplicate_files_num' => $result['duplicates'],
            ]);
            $this->localFileStatsService->generateStats($publicPath, $files);
            return $this->success('Duplicates Detected' . ($conflictsMessage ? ' (' . $conflictsMessage . ')' : ''), ['replaceAbort' => true]);
        }

        if ($result['successful'] > 0) {
            $this->localFileStatsService->generateStats($publicPath, $files);
            return $this->success('Files uploaded: ' . $result['successful'] . ' out of ' . count($files) . ($conflictsMessage ? ' (' . $conflictsMessage . ')' : ''));
        }

        return $this->error('Some/All Files upload failed' . ($conflictsMessage ? ' (' . $conflictsMessage . ')' : ''));
    }

    public function createItem(CreateItemRequest $request): RedirectResponse
    {
        $publicPath = $request->validated('path') ?? '';
        $itemName = $request->validated('itemName');
        $isFile = $request->validated('isFile');
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->pathService->genPrivatePathFromPublic($publicPath);
        if ($isFile
            && !$this->fileOperationsService->makeFile($this->pathService->getPlusContentRoot($publicPath, $itemName))
        ) {
            return $this->error('Create file failed');
        }
        if (!$isFile
            && !$this->fileOperationsService->makeFolder($this->pathService->getPlusContentRoot($publicPath, $itemName))
        ) {
            return $this->error('Create folder failed');
        }

        $this->localFileStatsService->addItemPathStat($itemName, $privatePath, $publicPath, !$isFile);
        return $this->success('Created ' . ($isFile ? 'file' : 'folder') . ' successfully');
    }


    public function abortReplace(ReplaceAbortRequest $request): RedirectResponse
    {
        if ($request->action === 'abort') {
            $this->uploadService->cleanOldTempFiles();
            $new_file_copied_num = session()->pull('new_file_copied_num') ?? 0;
            $duplicate_files_num = session()->pull('duplicate_files_num') ?? 0;

            return $this->success(
                'New files copied: ' . $new_file_copied_num . '. Files skipped: ' . $duplicate_files_num
            );
        }
        if ($request->action === 'overwrite') {
            $res = $this->uploadService->syncTempToStorage();
            if (!$res) {
                return $this->error('overwriting failed !');
            }

            $new_file_copied_num = session()->pull('new_file_copied_num') ?? 0;
            $duplicate_files_num = session()->pull('duplicate_files_num') ?? 0;

            return $this->success(
                'New files copied: ' . $new_file_copied_num . '. Files overwritten: ' . $duplicate_files_num
            );
        }
        return Redirect::back();
    }
}
