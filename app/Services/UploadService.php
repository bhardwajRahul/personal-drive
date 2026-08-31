<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Models\LocalFile;
use App\Models\Setting;
use Error;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class UploadService
{
    protected LocalFileStatsService $localFileStatsService;
    private PathService $pathService;
    private FileOperationsService $fileOperationsService;
    private ThumbnailService $thumbnailService;
    private Filesystem $filesystem;

    private string $tempUuid = 'temp_replace_dir_uuid';
    private string $tempUuidTime = 'temp_replace_dir_uuid_time';

    public function __construct(
        PathService $pathService,
        LocalFileStatsService $localFileStatsService,
        FileOperationsService $fileOperationsService,
        ThumbnailService $thumbnailService,
        Filesystem $filesystem
    ) {
        $this->pathService = $pathService;
        $this->localFileStatsService = $localFileStatsService;
        $this->fileOperationsService = $fileOperationsService;
        $this->thumbnailService = $thumbnailService;
        $this->filesystem = $filesystem;
    }

    public function setTempStorageDirAbs(): string
    {
        $tempStorageUuid = Str::uuid()->toString();
        Session::put($this->tempUuid, $tempStorageUuid);
        Session::put($this->tempUuidTime, now());

        return $this->getTempStorageDirAbs();
    }

    public function getTempStorageDirAbs(): string
    {
        $storagePath = Setting::getStoragePath();

        $tempStorageDir = $this->getTempStorageDir();
        if (!$tempStorageDir) {
            return '';
        }
        return $storagePath . DS . $tempStorageDir;
    }

    public function getTempStorageDir(): string
    {
        $tempUuid = Session::get($this->tempUuid);
        if (!$tempUuid) {
            return '';
        }

        return TEMP_SUBDIR . DS . $tempUuid;
    }

    public function syncTempToStorage(): bool
    {
        $tempDir = $this->getTempStorageDirAbs();
        $storageDir = $this->pathService->getStorageFolderPath();

        if (!$this->isValidDirectory($storageDir) || !$this->isValidDirectory($tempDir)) {
            return false;
        }

        foreach ($this->filesystem->allFiles($tempDir) as $file) {
            if (!$this->syncFileToStorage($file, $tempDir, $storageDir)) {
                return false;
            }
        }

        return true;
    }

    private function isValidDirectory(string $path = ''): bool
    {
        return $path &&
            $this->filesystem->exists($path) &&
            $this->filesystem->isDirectory($path);
    }

    public function syncFileToStorage(SplFileInfo $tempFileSplInfo, string $sourceRoot, string $targetRoot): bool
    {
        $targetPath = Str::replaceFirst($sourceRoot, $targetRoot, $tempFileSplInfo->getPathname());

        if (!$this->pathService->isWithinStorageRoot($targetPath)) {
            return false;
        }

        if ($this->filesystem->exists($targetPath)
            && $this->isFileFolderMisMatch($tempFileSplInfo->getPathname(), $targetPath)
        ) {
            return true;
        }

        $this->filesystem->ensureDirectoryExists(dirname($targetPath));
        $existingFile = LocalFile::getForFileObj($tempFileSplInfo);

        $this->filesystem->move($tempFileSplInfo->getPathname(), $targetPath);
        $newFile = new SplFileInfo($targetPath, dirname($targetPath), basename($targetPath));

        if (!$existingFile) {
            $itemDetails = $this->localFileStatsService->getFileItemDetails($newFile);
            $existingFile = LocalFile::updateOrCreate($itemDetails);
        } else {
            $this->localFileStatsService->updateFileStats($existingFile, $newFile);
        }

        $this->thumbnailService->genThumbnailsForFileIds([$existingFile->id]);

        return true;
    }

    public function isFileFolderMisMatch(string $file, string $directory): bool
    {
        return (
            ($this->filesystem->isFile($file) && $this->filesystem->isDirectory($directory)) ||
            ($this->filesystem->isDirectory($file) && $this->filesystem->isFile($directory))
        );
    }


    public function uploadToDir(string $destinationDir, mixed $file, string $publicPath): void
    {
        if (! $this->pathService->isWithinStorageRoot($destinationDir)) {
            throw UploadFileException::pathOutsideStorageRoot();
        }
        if (! $this->fileOperationsService->directoryExists($publicPath)) {
            if (! $this->pathService->isWithinStorageRoot(Setting::getStoragePath() . DS . $publicPath)) {
                throw UploadFileException::pathOutsideStorageRoot();
            }
            $this->fileOperationsService->makeFolder($publicPath);
        }
        $name = $this->pathService->sanitizeFileName($file->getClientOriginalName());
        try {
            if ($file->move($destinationDir, $name)) {
                chmod($destinationDir . DS . $name, 0640);
                return;
            }
        } catch (FileException) {
            throw UploadFileException::pathTooLong();
        } catch (Error) {
            throw UploadFileException::outOfMemory();
        }
    }

    public function cleanOldTempFiles(): bool
    {
        $tempDirFullPath = $this->getTempStorageDirAbs();
        if (!$tempDirFullPath) {
            return true;
        }
        if ($this->filesystem->exists($tempDirFullPath) 
            && $this->filesystem->isDirectory($tempDirFullPath)
        ) {
            Session::forget($this->tempUuid);
            Session::forget($this->tempUuidTime);
            return $this->filesystem->deleteDirectory($tempDirFullPath);
        }
        return false;
    }
}
