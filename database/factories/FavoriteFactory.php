<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'local_file_id' => LocalFile::factory(),
        ];
    }
}
