<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Builds the permission-aware sidebar from config/erp/navigation.php.
 */
final class Navigation
{
    /**
     * @return list<array{label: ?string, items: list<array{label: string, route: string, icon: string, url: string, active: bool}>}>
     */
    public static function for(User $user): array
    {
        $sections = [];

        foreach (config('erp.navigation') as $section) {
            $items = [];

            foreach ($section['items'] as $item) {
                if (! Route::has($item['route']) || ! $user->canAny((array) $item['permission'])) {
                    continue;
                }

                $items[] = [
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'icon' => $item['icon'],
                    'url' => route($item['route']),
                    'active' => request()->routeIs(self::routePattern($item['route'])),
                ];
            }

            if ($items !== []) {
                $sections[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $sections;
    }

    /**
     * "admin.users.index" is also active for "admin.users.*".
     */
    private static function routePattern(string $route): string
    {
        return str_ends_with($route, '.index') ? substr($route, 0, -6).'*' : $route;
    }
}
