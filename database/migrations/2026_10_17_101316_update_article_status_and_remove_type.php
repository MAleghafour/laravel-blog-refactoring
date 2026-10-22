
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('status')
                ->default('draft')
                ->change();

            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('type')->default('متنی');

            $table->enum('status', ['review', 'pending'])
                ->default('review')
                ->change();
        });
    }
};
