<?php

namespace App\Providers;

use App\Services\FakeLlmProvider;
use App\Services\LlmProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmProvider::class, FakeLlmProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('patientai', fn (Request $request) => Limit::perMinute(max(1, (int) config('patientai.messages_per_minute')))->by($request->user()->tenant_id.':'.$request->user()->id));
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
