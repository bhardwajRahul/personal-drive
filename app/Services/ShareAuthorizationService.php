<?php

namespace App\Services;

use App\Models\Share;
use App\Models\SharedFile;

class ShareAuthorizationService
{
    public function allowsFiles(int $shareId, array $fileIds): bool
    {
        $allFilesDirectlyShared = SharedFile::hasFileIdsInShare($shareId, $fileIds);
        $authorizedFiles = Share::getFilenamesByIds($shareId, $fileIds);

        return $allFilesDirectlyShared || $authorizedFiles->count() === count($fileIds);
    }
}
