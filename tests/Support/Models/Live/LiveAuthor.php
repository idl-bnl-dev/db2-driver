<?php

namespace IDL\DB2Driver\Tests\Support\Models\Live;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LiveAuthor extends LiveModel
{
    protected static function liveTable(): string
    {
        return 'authors';
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(LiveCountry::class, 'country_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(LiveProfile::class, 'author_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(LivePost::class, 'author_id');
    }

    public function mostViewedPost(): HasOne
    {
        return $this->hasOne(LivePost::class, 'author_id')->ofMany('views', 'max');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(LiveComment::class, 'commentable');
    }
}
