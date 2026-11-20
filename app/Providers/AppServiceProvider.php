<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureModelStrictness();
        $this->configureSlowQueryLogging();
        $this->configureHttps();
        $this->configureDevServerEnvironment();
    }

    /**
     * Define named rate limiters used by the API routes.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)
            ->by($request->ip()));

        RateLimiter::for('conversations', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Fail fast on lazy loading and missing attributes outside production.
     */
    private function configureModelStrictness(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
    }

    /**
     * Log slow queries in the local environment.
     */
    private function configureSlowQueryLogging(): void
    {
        if (! $this->app->isLocal()) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            if ($query->time <= (int) config('database.slow_query_ms', 200)) {
                return;
            }

            Log::debug('db.slow_query', [
                'sql' => $query->sql,
                'time_ms' => $query->time,
            ]);
        });
    }

    /**
     * Force HTTPS URLs when running in production.
     */
    private function configureHttps(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Pass the Reverb connection details through to the PHP dev server.
     *
     * `php artisan serve` only forwards a small allowlist of environment
     * variables to its child process. Docker Compose provides the Reverb
     * service hostname via the environment, but without this the child falls
     * back to the `.env` value (localhost) and server-side broadcasts fail.
     */
    private function configureDevServerEnvironment(): void
    {
        if (! $this->app->isLocal()) {
            return;
        }

        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables,
            'REVERB_HOST',
            'REVERB_PORT',
            'REVERB_SCHEME',
        ]));
    }
}
