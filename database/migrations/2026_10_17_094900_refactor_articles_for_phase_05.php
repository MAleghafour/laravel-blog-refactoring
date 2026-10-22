
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Allow incomplete article drafts.
            $table->string('title')->nullable()->change();
            $table->text('body')->nullable()->change();
            $table->foreignId('category_id')->nullable()->change();

            // Article lifecycle timestamps.
            $table->timestamp('first_published_at')->nullable();
            $table->timestamp('submitted_for_review_at')->nullable();

            // Soft deletion.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'first_published_at',
                'submitted_for_review_at',
            ]);

            $table->string('title')->nullable(false)->change();
            $table->text('body')->nullable(false)->change();
            $table->foreignId('category_id')
                ->nullable(false)
                ->change();
        });
    }
};
