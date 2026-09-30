<?php

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Reads config/erp/permissions.php and resolves role patterns ("module.*", "*").
 */
final class PermissionRegistry
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (config('erp.permissions') as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * @return array<string, array{label: string, actions: list<string>}>
     */
    public static function modules(): array
    {
        return config('erp.permissions');
    }

    /**
     * @param  list<string>  $patterns
     * @return list<string>
     */
    public static function expand(array $patterns): array
    {
        $all = self::all();
        $resolved = [];

        foreach ($patterns as $pattern) {
            $matches = array_values(array_filter($all, fn (string $name): bool => Str::is($pattern, $name)));

            if ($matches === []) {
                throw new InvalidArgumentException("Permission pattern [{$pattern}] matches nothing in config/erp/permissions.php.");
            }

            array_push($resolved, ...$matches);
        }

        return array_values(array_unique($resolved));
    }

    public static function label(string $permission): string
    {
        [$module, $action] = explode('.', $permission, 2);

        return Str::headline($action);
    }
}
