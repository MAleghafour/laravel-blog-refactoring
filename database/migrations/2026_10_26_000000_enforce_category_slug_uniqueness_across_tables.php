<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureSqlite();

        $conflict = DB::table('categories')
            ->join(
                'category_slug_histories',
                'category_slug_histories.slug',
                '=',
                'categories.slug'
            )
            ->select('categories.slug')
            ->first();

        if ($conflict !== null) {
            throw new RuntimeException(
                'Cannot install category slug triggers: slug "'
                .$conflict->slug
                .'" exists in both categories and category_slug_histories. '
                .'Resolve the conflict manually before retrying the migration.'
            );
        }

        DB::unprepared(<<<'SQL'
CREATE TRIGGER categories_slug_reject_history_insert
BEFORE INSERT ON categories
FOR EACH ROW
WHEN EXISTS (
    SELECT 1
    FROM category_slug_histories
    WHERE slug = NEW.slug
)
BEGIN
    SELECT RAISE(ABORT, 'category slug conflicts with category_slug_histories.slug');
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER categories_slug_reject_history_update
BEFORE UPDATE OF slug ON categories
FOR EACH ROW
WHEN EXISTS (
    SELECT 1
    FROM category_slug_histories
    WHERE slug = NEW.slug
)
BEGIN
    SELECT RAISE(ABORT, 'category slug conflicts with category_slug_histories.slug');
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER category_slug_histories_slug_reject_current_insert
BEFORE INSERT ON category_slug_histories
FOR EACH ROW
WHEN EXISTS (
    SELECT 1
    FROM categories
    WHERE slug = NEW.slug
)
BEGIN
    SELECT RAISE(ABORT, 'category slug history conflicts with categories.slug');
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER category_slug_histories_slug_reject_current_update
BEFORE UPDATE OF slug ON category_slug_histories
FOR EACH ROW
WHEN EXISTS (
    SELECT 1
    FROM categories
    WHERE slug = NEW.slug
)
BEGIN
    SELECT RAISE(ABORT, 'category slug history conflicts with categories.slug');
END
SQL);
    }

    public function down(): void
    {
        $this->ensureSqlite();

        DB::unprepared(
            'DROP TRIGGER IF EXISTS categories_slug_reject_history_insert'
        );
        DB::unprepared(
            'DROP TRIGGER IF EXISTS categories_slug_reject_history_update'
        );
        DB::unprepared(
            'DROP TRIGGER IF EXISTS category_slug_histories_slug_reject_current_insert'
        );
        DB::unprepared(
            'DROP TRIGGER IF EXISTS category_slug_histories_slug_reject_current_update'
        );
    }

    private function ensureSqlite(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                'Category slug cross-table protection requires SQLite; detected driver "'
                .$driver
                .'". No triggers were changed.'
            );
        }
    }
};
