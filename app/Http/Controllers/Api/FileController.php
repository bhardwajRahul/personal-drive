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
use App\Services\FileRenameService;
use App\Services\FileSaveService;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use App\Helpers\ResponseHelper;
use App\Services\UploadService;
use App\Traits\HasJsonPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class FileController extends Controller
{
    use HasJsonPagination;
    protected PathService $pathService;
    protected LocalFileStatsService $localFileStatsService;
    protected UploadService $uploadService;
    protected FileDeleteService $fileDeleteService;
    protected FileMoveService $fileMoveService;
    protected FileRenameService $fileRenameService;
    protected FileSaveService $fileSaveService;

    public function __construct(
        PathService $pathService,
        LocalFileStatsService $localFileStatsService,
        UploadService $uploadService,
        FileDeleteService $fileDeleteService,
        FileMoveService $fileMoveService,
        FileRenameService $fileRenameService,
        FileSaveService $fileSaveService,
    ) {
        $this->pathService = $pathService;
        $this->localFileStatsService = $localFileStatsService;
        $this->uploadService = $uploadService;
        $this->fileDeleteService = $fileDeleteService;
        $this->fileMoveService = $fileMoveService;
        $this->fileRenameService = $fileRenameService;
        $this->fileSaveService = $fileSaveService;
    }

    public function index(ListFilesRequest $request): JsonResponse
    {
        $path = $request->validated('path') ?? '';
        $rawPath = $path;
        $path = $this->pathService->cleanDrivePublicPath($path);
        $pathEcho = $rawPath !== '' && ltrim($rawPath, '/') === $path ? $rawPath : $path;

        $perPage = $request->validated('per_page', 50);

        $paginator = LocalFile::getFilesForPublicPath($path)
            ->paginate($perPage);

        $files = LocalFile::modifyFileCollectionForDrive($paginator->getCollection());
        $paginator->setCollection($files->values());

        return $this->paginateJson($paginator, 'files', ['path' => $pathEcho]);
    }

    public function show(string $id): JsonResponse
    {
        $file = LocalFile::getById($id);

        if (!$file || !file_exists($file->getPrivatePathNameForFile())) {
            return ResponseHelper::json('File not found', false, 404);
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

        if (!$files) {
            return ResponseHelper::json('No files uploaded', false, 422);
        }
        if (!$privatePath) {
            return ResponseHelper::json('Could not find storage path', false, 422);
        }

        $result = $this->uploadService->processFileUpload($files, $privatePath, $publicPath, swallowErrors: true);

        $this->localFileStatsService->generateStats($publicPath, $files);

        $message = $result['successful'] > 0
            ? 'Files uploaded: ' . $result['successful'] . ' out of ' . count($files)
            : 'Some/All files upload failed';

        if ($result['conflicts']) {
            $message .= ' (Conflicts: ' . $this->uploadService->summarizeConflicts($result['conflicts']) . ')';
        }

        $newFiles = LocalFile::modifyFileCollectionForDrive(
            LocalFile::getFilesForPublicPath($publicPath)->get()
        );

        return response()->json([
            'message' => $message,
            'files' => $newFiles->values(),
        ]);
    }

    public function create(CreateFileRequest $request): JsonResponse
    {
        $name = $request->validated('name');
        $type = $request->validated('type');
        $publicPath = $request->validated('path') ?? '';
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);

        $isFile = $type === 'file';
        $result = $this->fileSaveService->createItem($name, $publicPath, $isFile);

        if (!$result['success']) {
            return ResponseHelper::json($result['message'], false, 422);
        }

        $file = LocalFile::getByPathAndName($publicPath, $name);

        return response()->json([
            'message' => ucfirst($type) . ' created',
            'file' => $file,
        ]);
    }

    public function download(string $id)
    {
        $file = LocalFile::getById($id);

        if (!$file) {
            return ResponseHelper::json('File not found', false, 404);
        }

        $privatePath = $file->getPrivatePathNameForFile();

        if (!is_file($privatePath)) {
            return ResponseHelper::json('File not found on disk', false, 404);
        }

        $mimeType = mime_content_type($privatePath) ?: 'application/octet-stream';

        return response()->streamDownload(
            function () use ($privatePath) {
                readfile($privatePath);
            },
            $file->filename,
            ['Content-Type' => $mimeType]
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $file = LocalFile::getById($id);

        if (!$file) {
            return ResponseHelper::json('File not found', false, 404);
        }

        $rootPath = $this->pathService->getStorageFolderPath();
        $filesDeleted = $this->fileDeleteService->deleteFiles(
            LocalFile::getByIds([$id]),
            $rootPath
        );

        $file->delete();

        return response()->json([
            'message' => 'Files deleted',
            'deleted' => $filesDeleted,
        ]);
    }

    public function move(MoveFilesRequest $request): JsonResponse
    {
        $fileIds = $request->validated('fileList');
        $destination = $request->validated('destination');
        if ($destination === '/') {
            $destination = '';
        }

        $this->fileMoveService->moveFiles($fileIds, $destination);

        $newFiles = LocalFile::modifyFileCollectionForDrive(
            LocalFile::getFilesForPublicPath(
                $this->pathService->cleanDrivePublicPath($destination)
            )->get()
        );
        return response()->json([
            'message' => 'Files moved',
            'files' => $newFiles->values(),
        ]);
    }

    public function rename(RenameFileRequest $request, string $id): JsonResponse
    {
        $name = $request->validated('name');
        $file = LocalFile::getById($id);

        if (!$file) {
            return ResponseHelper::json('File not found', false, 404);
        }

        try {
            $this->fileRenameService->renameFile($file, $name);
        } catch (Exception $e) {
            Log::error('File rename failed', ['exception' => $e]);
            return ResponseHelper::json('Rename failed', false, 422);
        }

        $file->refresh();
        $file->sizeText = LocalFile::getItemSizeText($file);
        $file->date = filemtime($file->getPrivatePathNameForFile());

        return response()->json([
            'message' => 'File renamed',
            'file' => $file,
        ]);
    }

    public function save(SaveFileRequest $request, string $id): JsonResponse
    {
        $result = $this->fileSaveService->save($id, $request->validated('content'));

        if ($result['message'] === 'Could not find file') {
            return ResponseHelper::json($result['message'], false, 404);
        }

        if (!$result['success']) {
            return ResponseHelper::json($result['message'], false, 422);
        }

        return response()->json([
            'message' => 'File saved',
            'file' => $result['file']->fresh(),
        ]);
    }
}
