<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Configurable short lists (enquiry sources, close reasons, follow-up types …).
 * Records store the stable `code`; the display `name` may be renamed freely.
 */
#[Fillable(['type', 'code', 'name', 'sort_order', 'is_active'])]
class LookupValue extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    public const ENQUIRY_SOURCE = 'enquiry_source';

    public const CLOSE_REASON = 'enquiry_close_reason';

    public const FOLLOW_UP_TYPE = 'follow_up_type';

    /**
     * Types administrators may maintain, with their labels.
     */
    public const TYPES = [
        self::ENQUIRY_SOURCE => 'Enquiry sources',
        self::CLOSE_REASON => 'Lost / dropped reasons',
        self::FOLLOW_UP_TYPE => 'Follow-up types',
    ];

    protected string $auditModule = 'settings';

    /**
     * Active options of a type as code => name.
     *
     * @return Collection<string, string>
     */
    public static function options(string $type): Collection
    {
        return static::query()->where('type', $type)->active()->orderBy('sort_order')->orderBy('name')->pluck('name', 'code');
    }

    /**
     * Name for a stored code, including inactive values so history still reads correctly.
     */
    public static function label(string $type, ?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return once(fn () => static::query()->where('type', $type)->pluck('name', 'code'))[$code] ?? $code;
    }
}
