<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedFile extends Model
{
    use HasFactory;


    public function share(): BelongsTo
    {
        return $this->belongsTo(Share::class);
    }

    public function localFile(): BelongsTo
    {
        return $this->belongsTo(LocalFile::class, 'file_id');
    }

    public static function addArray(Collection $localFiles, int $shareId): bool
    {
        $sharedFiles = $localFiles->map(fn($localFile) => self::getFileIds($shareId, $localFile))->toArray();
        return SharedFile::insert($sharedFiles);
    }

    private static function getFileIds(int $shareId, LocalFile $file): array
    {
        return [
            'share_id' => $shareId,
            'file_id' => $file->id,
        ];
    }
}
