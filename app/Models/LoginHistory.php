<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'identifier', 'event', 'channel', 'ip_address', 'user_agent'])]
class LoginHistory extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_SUCCESS = 'success';

    public const EVENT_FAILED = 'failed';

    public const EVENT_LOCKED = 'locked';

    public const EVENT_INACTIVE = 'inactive';

    public const EVENT_LOGOUT = 'logout';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
