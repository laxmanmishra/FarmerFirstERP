<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Livewire resolves these names on `$wire` to its own client helpers, so a component
 * action with the same name is never called from the browser (e.g. `wire:submit="upload"`
 * starts a file upload instead). Server-side tests cannot catch that, this test does.
 */
class LivewireReservedNamesTest extends TestCase
{
    private const RESERVED = [
        'on', 'el', 'id', 'js', 'get', 'set', 'refs', 'call', 'hook', 'watch', 'dirty', 'effect', 'commit', 'errors', 'island',
        'upload', 'entangle', 'dispatch', 'intercept', 'interceptAction', 'interceptMessage', 'interceptRequest', 'dispatchTo',
        'dispatchSelf', 'dispatchEl', 'dispatchRef', 'removeUpload', 'cancelUpload', 'uploadMultiple',
    ];

    public function test_no_component_action_shadows_a_livewire_client_helper(): void
    {
        $clashes = [];
        $scanned = 0;

        foreach (File::allFiles(app_path('Livewire')) as $file) {
            $class = 'App\\Livewire\\'.Str::of($file->getRelativePathname())->replace(['/', '\\'], '\\')->beforeLast('.php');

            if (! class_exists($class) || ! is_subclass_of($class, Component::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $scanned++;

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() === $class && in_array($method->getName(), self::RESERVED, true)) {
                    $clashes[] = $class.'::'.$method->getName();
                }
            }
        }

        $this->assertGreaterThan(30, $scanned);
        $this->assertSame([], $clashes, 'Rename these Livewire actions; the names are reserved on $wire.');
    }
}
