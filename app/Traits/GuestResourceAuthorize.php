<?php

namespace App\Traits;

use App\Services\ShareAuthorizationService;
use Illuminate\Support\Facades\Session;

trait GuestResourceAuthorize
{
    protected function guestVerified(array $fileIds, ShareAuthorizationService $shareAuthorizationService): bool
    {
        $shareId = Session::get('share_id');
        if (! $shareId) {
            return false;
        }

        if (! $shareAuthorizationService->allowsFiles($shareId, $fileIds)) {
            return false;
        }

        return true;
    }
}
