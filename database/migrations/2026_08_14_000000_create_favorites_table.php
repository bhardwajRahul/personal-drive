<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('local_file_id')->constrained('local_files')->cascadeOnDelete();
            $table->timestamp('favorited_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'local_file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
