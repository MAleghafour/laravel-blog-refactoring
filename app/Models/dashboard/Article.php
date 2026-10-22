<?php

namespace App\Models\dashboard;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'lock_version' => 0,
    ];

    protected $fillable = [
        'title',
        'body',
        'slug',
        'brief',
        'thumbnail_small',
        'thumbnail_medium',
        'thumbnail_large',
        'tags',
        'author_id',
        'category_id',
        'status',
        'first_published_at',
        'submitted_for_review_at',
    ];

    protected function casts(): array
    {
        return [
            'first_published_at' => 'datetime',
            'submitted_for_review_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Article $article) {
            if ($article->isDirty()) {
                $article->lock_version = $article->lock_version + 1;
            }
        });
    }

    protected function runSoftDelete()
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [
            $this->getDeletedAtColumn() => $this->fromDateTime($time),
        ];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $this->lock_version = (int) $this->lock_version + 1;

        $columns['lock_version'] = $this->lock_version;

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ArticleEvent::class);
    }
}
