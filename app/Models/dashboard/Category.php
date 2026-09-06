<?php

namespace App\Models\dashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    //
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon'
    ];

    protected  static function boot()
    {
        parent::boot();
        static::deleting(function ($category)
        {
            $category->articles()->delete();
        });
    }

    public function articles(): hasMany
    {
        return $this->hasMany(Article::class);
    }
}
