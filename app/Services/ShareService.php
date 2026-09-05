<?php

namespace App\Services;

use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareService
{
    public function create(array $fileIds, ?string $slug = '', ?string $password = '', ?string $expiry = ''): array
    {
        $localFiles = LocalFile::getByIds($fileIds)->get();

        if ($localFiles->count() !== count($fileIds)) {
            return ['success' => false, 'message' => 'Some files not found'];
        }

        $slug = $slug ?: Str::random(10);
        $hashedPassword = $password ? Hash::make($password) : '';
        $share = Share::add($slug, $hashedPassword, $expiry, $localFiles->first()->public_path);

        if (!SharedFile::addArray($localFiles, $share->id)) {
            $share->delete();

            return ['success' => false, 'message' => 'No valid files to share. Try a Resync'];
        }

        return [
            'success' => true,
            'message' => 'Share created',
            'share' => $share,
            'url' => url('/shared/' . $slug),
        ];
    }


    public function toggle(int $id): array
    {
        $share = Share::whereById($id)->first();

        if (!$share) {
            return ['success' => false, 'message' => 'Share not found'];
        }

        $share->forceFill(['enabled' => !$share->enabled])->save();

        return [
            'success' => true,
            'message' => $share->enabled ? 'Share enabled' : 'Share paused',
            'share' => $share,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function delete(int $id): array
    {
        if (!Share::whereById($id)->delete()) {
            return ['success' => false, 'message' => 'Share not found'];
        }

        return ['success' => true, 'message' => 'Share deleted'];
    }
}
