<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateFileRequest;
use App\Http\Requests\Api\ListFilesRequest;
use App\Http\Requests\Api\MoveFilesRequest;
use App\Http\Requests\Api\RenameFileRequest;
use App\Http\Requests\Api\SaveFileRequest;
use App\Http\Requests\Api\UploadFilesRequest;
use App\Models\LocalFile;
use App\Services\FileDeleteService;
use App\Services\FileMoveService;
use App\Services\FileOperationsService;
use App\Services\FileRenameService;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use App\Services\UploadService;
use App\Traits\HasJsonPagination;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SplFileInfo;

class FileController extends Controller
{
    use HasJsonPagination;
    protected PathService $pathService;
    protected FileOperationsService $fileOperationsService;
    protected LocalFileStatsService $localFileStatsService;
    protected UploadService $uploadService;
    protected FileDeleteService $fileDeleteService;
    protected FileMoveService $fileMoveService;
    protected FileRenameService $fileRenameService;

    public function __construct(
        PathService $pathService,
        FileOperationsService $fileOperationsService,
        LocalFileStatsService $localFileStatsService,
        UploadService $uploadService,
        FileDeleteService $fileDeleteService,
        FileMoveService $fileMoveService,
        FileRenameService $fileRenameService,
    ) {
        $this->pathService = $pathService;
        $this->fileOperationsService = $fileOperationsService;
        $this->localFileStatsService = $localFileStatsService;
        $this->uploadService = $uploadService;
        $this->fileDeleteService = $fileDeleteService;
        $this->fileMoveService = $fileMoveService;
        $this->fileRenameService = $fileRenameService;
    }

    public function index(ListFilesRequest $request): JsonResponse
    {
        $path = $request->validated('path') ?? '';
        $path = $this->pathService->cleanDrivePublicPath($path);

        $perPage = $request->validated('per_page', 50);

        $paginator = LocalFile::getFilesForPublicPath($path)
            ->paginate($perPage);

        $files = LocalFile::modifyFileCollectionForDrive($paginator->getCollection());
        $paginator->setCollection($files->values());

        return $this->paginateJson($paginator, 'files', ['path' => $path]);
    }

    public function show(string $id): JsonResponse
    {
        $file = LocalFile::getById($id);

        if (! $file || ! file_exists($file->getPrivatePathNameForFile())) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $file->sizeText = LocalFile::getItemSizeText($file);
        $file->date = filemtime($file->getPrivatePathNameForFile());

        return response()->json(['file' => $file]);
    }

    public function upload(UploadFilesRequest $request): JsonResponse
    {
        $files = $request->validated('files') ?? [];
        $publicPath = $request->validated('path') ?? '';
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->pathService->genPrivatePathFromPublic($publicPath);

        if (! $files) {
            return response()->json(['message' => 'No files uploaded'], 422);
        }
        if (! $privatePath) {
            return response()->json(['message' => 'Could not find storage path'], 422);
        }

        $uploadedFiles = [];
        $errors = [];

        $tempStorageDirFull = $this->uploadService->setTempStorageDirAbs();

        foreach ($files as $file) {
            $sanitizedPath = $this->pathService->sanitizeUploadPath($file->getClientOriginalPath());
            $sanitizedName = $this->pathService->sanitizeFileName($file->getClientOriginalName());

            if ($sanitizedPath === '' || $sanitizedName === '') {
                $errors[] = $file->getClientOriginalName();
                continue;
            }

            $destinationFullPath = $privatePath . $sanitizedPath;
            $destDir = dirname($destinationFullPath);
            $relativeBasePath = $this->pathService->getPlusContentRoot($publicPath);
            $relativeDestinationPath = $relativeBasePath . $sanitizedPath;

            if ($this->fileOperationsService->directoryExists($relativeDestinationPath)
                || $this->fileOperationsService->pathExistsAsFile(
                    $relativeBasePath,
                    dirname($sanitizedPath)
                )
            ) {
                $tempDirFullPath = dirname(
                    $this->uploadService->getTempStorageDirAbs() . DS . ($publicPath ? $publicPath . DS : '') . $sanitizedPath
                );
                $tempDirRelativePath = $this->uploadService->getTempStorageDir() . DS . $publicPath;
                $this->uploadToDir($tempDirFullPath, $file, $tempDirRelativePath);
                continue;
            }

            $result = $this->uploadToDir($destDir, $file, dirname($relativeDestinationPath));
            if ($result > 0) {
                $uploadedFiles[] = $sanitizedName;
            }
        }

        $this->localFileStatsService->generateStats($publicPath, $files);

        $newFiles = LocalFile::getFilesForPublicPath($publicPath)->get();

        return response()->json(
            [
            'message' => 'Files uploaded',
            'files' => $newFiles->values(),
            ]
        );
    }

    private function uploadToDir(string $destinationDir, mixed $file, string $publicPath): int
    {
        if (! $this->pathService->isWithinStorageRoot($destinationDir)) {
            return 0;
        }

        if (! $this->fileOperationsService->directoryExists($publicPath)) {
            $this->fileOperationsService->makeFolder($publicPath);
        }

        $sanitizedName = $this->pathService->sanitizeFileName($file->getClientOriginalName());

        try {
            if ($file->move($destinationDir, $sanitizedName)) {
                chmod($destinationDir . DS . $sanitizedName, 0640);
                return 1;
            }
        } catch (Exception) {
            // skip failed uploads
        }

        return 0;
    }

    public function create(CreateFileRequest $request): JsonResponse
    {
        $name = $request->validated('name');
        $type = $request->validated('type');
        $publicPath = $request->validated('path') ?? '';
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->pathService->genPrivatePathFromPublic($publicPath);

        $isFile = $type === 'file';

        if ($isFile) {
            $created = $this->fileOperationsService->makeFile(
                $this->pathService->getPlusContentRoot($publicPath, $name)
            );
        } else {
            $created = $this->fileOperationsService->makeFolder(
                $this->pathService->getPlusContentRoot($publicPath, $name)
            );
        }

        if (! $created) {
            return response()->json(['message' => 'Create ' . $type . ' failed'], 422);
        }

        $this->localFileStatsService->addItemPathStat($name, $privatePath, $publicPath, ! $isFile);

        $file = LocalFile::where('filename', $name)
            ->where('public_path', $publicPath)
            ->first();

        return response()->json(
            [
            'message' => ucfirst($type) . ' created',
            'file' => $file,
            ]
        );
    }

    public function download(string $id)
    {
        $file = LocalFile::getById($id);

        if (! $file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $privatePath = $file->getPrivatePathNameForFile();

        if (! is_file($privatePath)) {
            return response()->json(['message' => 'File not found on disk'], 404);
        }

        $mimeType = mime_content_type($privatePath) ?: 'application/octet-stream';

        return response()->streamDownload(
            function () use ($privatePath) {
                readfile($privatePath);
            }, $file->filename, [
            'Content-Type' => $mimeType,
            ]
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $file = LocalFile::getById($id);

        if (! $file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $rootPath = $this->pathService->getStorageFolderPath();
        $localFiles = LocalFile::getByIds([$id]);
        $filesDeleted = $this->fileDeleteService->deleteFiles($localFiles, $rootPath);

        $localFiles->delete();

        return response()->json(
            [
            'message' => 'Files deleted',
            'deleted' => $filesDeleted,
            ]
        );
    }

    public function move(MoveFilesRequest $request): JsonResponse
    {
        $fileIds = $request->validated('fileList');
        $destination = $request->validated('destination');

        $this->fileMoveService->moveFiles($fileIds, $destination);

        $newFiles = LocalFile::getFilesForPublicPath(
            $this->pathService->cleanDrivePublicPath($destination)
        )->get();

        return response()->json(
            [
            'message' => 'Files moved',
            'files' => $newFiles->values(),
            ]
        );
    }

    public function rename(RenameFileRequest $request, string $id): JsonResponse
    {
        $name = $request->validated('name');
        $file = LocalFile::getById($id);

        if (! $file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        try {
            $this->fileRenameService->renameFile($file, $name);
        } catch (Exception $e) {
            Log::error('File rename failed', ['exception' => $e]);
            return response()->json(['message' => 'Rename failed'], 422);
        }

        $file->refresh();
        $file->sizeText = LocalFile::getItemSizeText($file);
        $file->date = filemtime($file->getPrivatePathNameForFile());

        return response()->json(
            [
            'message' => 'File renamed',
            'file' => $file,
            ]
        );
    }

    public function save(SaveFileRequest $request, string $id): JsonResponse
    {
        $content = $request->validated('content');
        $file = LocalFile::getById($id);

        if (! $file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        if ($file->file_type !== 'text' && $file->file_type !== 'empty') {
            return response()->json(['message' => 'File is not a text file'], 422);
        }

        $privatePathFile = $file->getPrivatePathNameForFile();

        if (! $privatePathFile || ! is_file($privatePathFile) || ! is_writable($privatePathFile)) {
            return response()->json(['message' => 'Could not save file'], 422);
        }

        if (file_put_contents($privatePathFile, $content) === false) {
            return response()->json(['message' => 'Could not save file'], 422);
        }

        $fileInfo = new SplFileInfo($privatePathFile);
        $this->localFileStatsService->updateFileStats($file, $fileInfo);

        $file->refresh();

        return response()->json(
            [
            'message' => 'File saved',
            'file' => $file,
            ]
        );
    }
}
