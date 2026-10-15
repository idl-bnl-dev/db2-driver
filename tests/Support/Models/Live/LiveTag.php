<?php

namespace IDL\DB2Driver\Tests\Support\Models\Live;

use IDL\DB2Driver\Tests\Support\LiveSchema;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LiveTag extends LiveModel
{
    protected static function liveTable(): string
    {
        return 'tags';
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(LivePost::class, LiveSchema::table('post_tag'), 'tag_id', 'post_id');
    }
}
