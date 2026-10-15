<?php

namespace IDL\DB2Driver\Tests\Support\Models\Live;

use IDL\DB2Driver\Tests\Support\LiveSchema;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LivePost extends LiveModel
{
    protected static function liveTable(): string
    {
        return 'posts';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(LiveAuthor::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(LiveTag::class, LiveSchema::table('post_tag'), 'post_id', 'tag_id')->withPivot('weight');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(LiveComment::class, 'commentable');
    }
}
