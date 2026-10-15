<?php

namespace IDL\DB2Driver\Tests\Support\Models\Live;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class LiveComment extends LiveModel
{
    protected static function liveTable(): string
    {
        return 'comments';
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
