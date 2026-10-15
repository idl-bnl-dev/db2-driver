<?php

namespace IDL\DB2Driver\Tests\Support\Models\Live;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class LiveCountry extends LiveModel
{
    protected static function liveTable(): string
    {
        return 'countries';
    }

    public function authors(): HasMany
    {
        return $this->hasMany(LiveAuthor::class, 'country_id');
    }

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(LivePost::class, LiveAuthor::class, 'country_id', 'author_id');
    }

    public function firstPost(): HasOneThrough
    {
        return $this->hasOneThrough(LivePost::class, LiveAuthor::class, 'country_id', 'author_id')->orderBy('views', 'desc');
    }
}
