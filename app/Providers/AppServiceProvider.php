<?php

namespace App\Providers;

use App\Enums\ReportableType;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // The mobile app reads resources as plain objects and arrays.
        JsonResource::withoutWrapping();

        // Short, stable type names in polymorphic columns (reports, tokens).
        Relation::enforceMorphMap(collect(ReportableType::cases())
            ->mapWithKeys(fn (ReportableType $type) => [$type->value => $type->modelClass()])
            ->all());

        Gate::define('admin', fn (User $user): bool => $user->is_admin);

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));

        RateLimiter::for('reports', fn (Request $request) => Limit::perHour(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));

        ResetPassword::createUrlUsing(fn (User $user, string $token): string => route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));
    }
}
