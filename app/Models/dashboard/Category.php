<?php

namespace App\Models\dashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $attributes = [
        'lock_version' => 0,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Category $category) {
            if ($category->isDirty()) {
                $category->lock_version = $category->lock_version + 1;
            }
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function slugHistories(): HasMany
    {
        return $this->hasMany(CategorySlugHistory::class);
    }
}
