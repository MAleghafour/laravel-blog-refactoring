<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_slug_histories', function (Blueprint $table) {
            $table->id();

            // Keep the original category ID even after permanent deletion.
            $table->unsignedBigInteger('category_id')->index();

            // Reserve historical slugs to prevent future reuse.
            $table->string('slug')->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_slug_histories');
    }
};
