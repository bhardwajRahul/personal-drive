<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Models\LocalFile;
use App\Models\Share;
use App\Services\LocalFileStatsService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class ReSyncController extends Controller
{
    use FlashMessages;

    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        LocalFileStatsService $localFileStatsService
    ) {
        $this->localFileStatsService = $localFileStatsService;
    }

    public function index(): RedirectResponse
    {
        try {
            $filesUpdated = DB::transaction(function (): int {
                LocalFile::clearTable();
                Share::truncate();
                return $this->localFileStatsService->generateStats();
            });
        } catch (UnexpectedValueException) {
            return $this->error(
                'Storage scan failed because a file or folder cannot be accessed. Check its permissions and try again.'
            );
        }
        if ($filesUpdated > 0) {
            return $this->success('Sync successful. Found : ' . $filesUpdated . ' files');
        }

        return $this->error('No files found');
    }
}
