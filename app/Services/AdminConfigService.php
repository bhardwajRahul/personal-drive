<?php

namespace App\Services;

use App\Models\LocalFile;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Throwable;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class AdminConfigService
{
    protected FileOperationsService $fileOperationsService;
    protected LocalFileStatsService $localFileStatsService;
    private Setting $setting;

    public function __construct(
        FileOperationsService $fileOperationsService,
        LocalFileStatsService $localFileStatsService,
        Setting $setting
    ) {
        $this->fileOperationsService = $fileOperationsService;
        $this->localFileStatsService = $localFileStatsService;
        $this->setting = $setting;
    }

    public function updateStoragePath(string $storagePath): array
    {
        $storagePath = trim(rtrim($storagePath, '/'));

        try {
            DB::beginTransaction();

            $result = $this->applyStoragePath($storagePath);

            DB::commit();
            return $result;
        } catch (UnexpectedValueException) {
            DB::rollBack();
            return ['status' => false, 'message' => 'Scan failed. Check permissions.'];
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update storage path', ['exception' => $e]);
            return ['status' => false, 'message' => 'Unable to update storage path'];
        }
    }

    private function applyStoragePath(string $storagePath): array
    {
        if (!$this->updateSetting($storagePath)) {
            return ['status' => false, 'message' => 'Failed to save storage path setting'];
        }

        if (!$this->ensureDirectoryExists(CONTENT_SUBDIR)) {
            $this->revertSetting();
            return ['status' => false, 'message' => 'Unable to create storage directory. Check Permissions'];
        }

        if (!$this->ensureDirectoryExists(THUMBS_SUBDIR)) {
            $this->revertSetting();
            return ['status' => false, 'message' => 'Unable to create thumbnail directory. Check Permissions'];
        }

        LocalFile::clearTable();
        $this->localFileStatsService->generateStats();

        return ['status' => true, 'message' => 'Storage path updated successfully'];
    }

    public function updateSetting(string $storagePath): bool
    {
        $res = $this->setting->updateStoragePath($storagePath);
        if ($res) {
            $this->fileOperationsService->setFilesystem(null);
        }
        return $res;
    }

    protected function ensureDirectoryExists(string $path): bool
    {
        if ($this->fileOperationsService->directoryExists($path)) {
            return $this->fileOperationsService->isWritable($path);
        }

        return $this->fileOperationsService->makeFolder($path) && $this->fileOperationsService->isWritable($path);
    }

    private function revertSetting(): void
    {
        $this->setting->revertStoragePath();
    }

    public function getPhpUploadMaxFilesize(): string
    {
        return (string) ini_get('upload_max_filesize');
    }

    public function getPhpPostMaxSize(): string
    {
        return (string) ini_get('post_max_size');
    }

    public function getPhpMaxFileUploads(): string
    {
        return (string) ini_get('max_file_uploads');
    }
}
