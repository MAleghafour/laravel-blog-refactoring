<?php

namespace App\Models\dashboard;

use Illuminate\Database\Eloquent\Model;

class CategorySlugHistory extends Model
{
    protected $fillable = [
        'category_id',
        'slug',
    ];
}
