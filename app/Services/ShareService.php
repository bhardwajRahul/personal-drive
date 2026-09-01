<?php

namespace App\Services;

use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareService
{
    /**
     * @param array<string> $fileIds
     * @return array{success: bool, message: string, share?: Share, url?: string}
     */
    public function create(array $fileIds, string $slug = '', string $password = '', string $expiry = ''): array
    {
        $slug = $slug ?: Str::random(10);

        $localFiles = LocalFile::whereIn('id', $fileIds)->get();

        if ($localFiles->count() !== count($fileIds)) {
            return [
                'success' => false,
                'message' => 'Some files not found',
            ];
        }

        $hashedPassword = $password ? Hash::make($password) : '';

        $share = Share::add($slug, $hashedPassword, $expiry, $localFiles->first()->public_path);
        SharedFile::addArray($localFiles, $share->id);

        return [
            'success' => true,
            'message' => 'Share created',
            'share' => $share,
            'url' => url('/shared/' . $slug),
        ];
    }

    public function toggle(Share $share): Share
    {
        $share->forceFill(['enabled' => !$share->enabled])->save();
        return $share->fresh();
    }
}
