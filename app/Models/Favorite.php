<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = ['user_id', 'local_file_id'];

    protected $hidden = ['user_id', 'local_file_id'];

    protected function casts(): array
    {
        return [
            'favorited_at' => 'datetime',
        ];
    }

    public static function listForCurrentUser(): Builder
    {
        return static::with('localFile:id,filename,public_path,is_dir')
            ->where('user_id', auth()->user()->id)
            ->orderByDesc('favorited_at')
            ->orderByDesc('id');
    }

    public static function removeForUser(string $id): bool
    {
        return static::where('id', $id)
            ->where('user_id', auth()->user()->id)
            ->delete() > 0;
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
