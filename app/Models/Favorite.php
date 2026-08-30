<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = ['user_id', 'local_file_id'];

    protected static function booted(): void
    {
        static::creating(
            function (self $favorite): void {
                $favorite->favorited_at ??= now();
            }
        );
    }

    protected function casts(): array
    {
        return [
            'favorited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function localFile(): BelongsTo
    {
        return $this->belongsTo(LocalFile::class);
    }
}
