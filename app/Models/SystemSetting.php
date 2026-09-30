<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['group', 'key', 'value', 'description'])]
class SystemSetting extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'settings';

    private const CACHE_KEY = 'erp.system_settings';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general', ?string $description = null): self
    {
        $setting = static::query()->firstOrNew(['key' => $key]);
        $setting->fill(['group' => $setting->group ?? $group, 'value' => $value]);

        if ($description !== null) {
            $setting->description = $description;
        }

        $setting->save();

        return $setting;
    }
}
