<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerBlueprintMacros();
        $this->configureModels();
        $this->configureAuthorization();
        $this->configureRateLimiting();

        // Re-run on every Livewire update request, not only on the initial page load.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());
    }

    /**
     * Adds `$table->userstamps()` for the created_by / updated_by audit columns (SRS §33).
     */
    private function registerBlueprintMacros(): void
    {
        Blueprint::macro('userstamps', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Outside production, fail loudly on N+1 lazy loading and on mass-assignment
     * of non-fillable attributes.
     */
    private function configureModels(): void
    {
        $strict = ! app()->isProduction();

        Model::preventLazyLoading($strict);
        Model::preventSilentlyDiscardingAttributes($strict);
    }

    /**
     * Super Admin bypasses permission checks. Owner is granted permissions explicitly
     * so that the role matrix stays visible and auditable.
     */
    private function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(User::SUPER_ADMIN_ROLE) ? true : null);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by(
            strtolower((string) $request->input('email')).'|'.$request->ip()
        ));
    }
}
