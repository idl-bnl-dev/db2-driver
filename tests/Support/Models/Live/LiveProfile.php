<?php

namespace IDL\DB2Driver\Tests\Support\Models\Live;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveProfile extends LiveModel
{
    protected static function liveTable(): string
    {
        return 'profiles';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(LiveAuthor::class, 'author_id');
    }
}
