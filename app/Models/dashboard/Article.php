<?php

namespace App\Models\dashboard;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    //
    protected $fillable = [
        'title',
        'type',
        'body',
        'slug',
        'brief',
        'thumbnail_small',
        'thumbnail_medium',
        'thumbnail_large',
        'tags',
        'author_id',
        'category_id',
        'status'
    ];


    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category() :BelongsTo
    {
     return $this->belongsTo(Category::class);
    }


}
