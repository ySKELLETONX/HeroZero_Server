<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->configureRateLimiting();
    }

    /**
     * Rate limiters de app (defense-in-depth; a 1a linha e o limit_req do Nginx).
     * Limites configuraveis por env para ajustar sem redeploy.
     *
     *  - "game":  /request.php — jogo faz polling, entao limite generoso por IP.
     *  - "auth":  /beta-api — registro/login, alvo de brute-force -> limite baixo.
     */
    private function configureRateLimiting(): void
    {
        $gamePerMin = (int) (getenv('HZ_RATE_GAME_PER_MIN') ?: 300);
        $authPerMin = (int) (getenv('HZ_RATE_AUTH_PER_MIN') ?: 20);

        RateLimiter::for('game', fn (Request $request) => Limit::perMinute($gamePerMin)->by($request->ip()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute($authPerMin)->by($request->ip()));
    }
}
