<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileRenameException;
use App\Models\LocalFile;
use Illuminate\Support\Facades\DB;

class FileRenameService
{
    protected FileOperationsService $fileOperationsService;
    private PathService $pathService;

    public function __construct(
        PathService $pathService,
        FileOperationsService $fileOperationsService,
    ) {
        $this->pathService = $pathService;
        $this->fileOperationsService = $fileOperationsService;
    }

    public function renameFile(LocalFile $file, string $newFilename): void
    {
        $storageFolderName = CONTENT_SUBDIR;
        $itemPathName = $storageFolderName . \DS . $file->getPublicPathname();
        $itemPublicDestPathName = $storageFolderName . DS . $file->getPublicPath() . $newFilename;

        if ($this->fileOperationsService->fileExists($itemPublicDestPathName)) {
            throw FileRenameException::couldNotRename();
        }

        $this->fileOperationsService->move($itemPathName, $itemPublicDestPathName);
        if ($file->is_dir) {
            $this->updateDirChildrenRecursively($file, $newFilename);
        }

        $updated = $file->update(['filename' => $newFilename]);
        if (!$updated) {
            throw FileRenameException::couldNotUpdateIndex();
        }
    }

    public function updateDirChildrenRecursively(LocalFile $file, string $newFilename): void
    {
        $dirPublicPathname = $file->getPublicPathname();
        $newFolderPublicPath = $file->getPublicPath() . $newFilename;
        LocalFile::getByPublicPathLikeSearch($dirPublicPathname)
            ->chunk(
                100,
                function ($childFiles) use ($dirPublicPathname, $newFolderPublicPath) {
                    $updates = [];
                    foreach ($childFiles as $childFile) {
                        $newPublicPath = $newFolderPublicPath . substr(
                            $childFile->public_path,
                            strlen($dirPublicPathname)
                        );
                        $newPrivatePath = $this->pathService->genPrivatePathFromPublic($newPublicPath);
                        $updates [] = [
                            'id' => $childFile->id,
                            'public_path' => $newPublicPath,
                            'private_path' => $newPrivatePath
                        ];
                    }

                    foreach ($updates as $update) {
                        DB::table('local_files')
                            ->where('id', $update['id'])
                            ->update([
                                'public_path' => $update['public_path'], 'private_path' => $update['private_path']
                            ]);
                    }
                }
            );
    }
}
