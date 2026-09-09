<?php

namespace App\Services;

use App\Models\LocalFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

class FileDeleteService
{
    private array $deletedIds = [];

    private function checkAccess(string $path): string
    {
        $parent = dirname($path);

        if (!is_executable($parent)) {
            return 'unreadable';
        }

        if (!file_exists($path)) {
            return 'already_deleted';
        }

        if (!is_writable($parent)) {
            return 'readonly';
        }

        return 'deletable';
    }

    public function deleteFiles(Builder $filesInDB, string $rootStoragePath): array
    {
        $this->deletedIds = [];
        $unreadableIds = [];
        $readonlyIds = [];

        foreach ($filesInDB->get() as $file) {
            $path = $file->getPrivatePathNameForFile();

            switch ($this->checkAccess($path)) {
                case 'unreadable':
                    $unreadableIds[] = $file->id;
                    continue 2;

                case 'readonly':
                    $readonlyIds[] = $file->id;
                    continue 2;

                case 'already_deleted':
                    $this->deletedIds[] = $file->id;
                    continue 2;

                case 'deletable':
                    if ($this->handleDirectoryDeletion($file, $path, $rootStoragePath)) {
                        $this->deletedIds[] = $file->id;
                        continue 2;
                    }

                    if (
                        $this->isDeletableFile($file) &&
                        $this->isPathWithinStorage($path, $rootStoragePath) &&
                        @unlink($path) &&
                        !file_exists($path)
                    ) {
                        $this->deletedIds[] = $file->id;
                        continue 2;
                    }

                    $readonlyIds[] = $file->id; // looked deletable but failed anyway (race condition)
            }
        }

        if ($this->deletedIds) {
            LocalFile::getByIds($this->deletedIds)->delete();
        }

        return [
            'deleted' => $this->deletedIds,
            'unreadable' => $unreadableIds,
            'readonly' => $readonlyIds,
        ];
    }

    protected function handleDirectoryDeletion(
        LocalFile $file,
        string $privateFilePathName,
        string $rootStoragePath
    ): bool {
        if (
            $this->isDeletableDirectory($file, $privateFilePathName)
            && $this->isPathWithinStorage($privateFilePathName, $rootStoragePath)
        ) {
            if (File::deleteDirectory($privateFilePathName)) {
                $file->deleteUsingPublicPath();
                return true;
            }
        }
        return false;
    }

    public function isDeletableDirectory(LocalFile $file, string $privateFilePathName): bool
    {
        return $file->is_dir && file_exists($privateFilePathName) && is_dir($privateFilePathName);
    }

    public function isPathWithinStorage(string $privatePathName, string $rootStoragePath): bool
    {
        $path = realpath($privatePathName);
        $root = realpath($rootStoragePath);

        if ($path === false || $root === false) {
            return false;
        }

        return $path === $root || str_starts_with($path, rtrim($root, DS) . DS);
    }

    public function isDeletableFile(LocalFile $file): bool
    {
        return !$file->is_dir ;
    }
}
