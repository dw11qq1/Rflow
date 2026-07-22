<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use App\Models\Card;
use App\Models\Column;
use App\Observers\CardObserver;
use App\Observers\ColumnObserver;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Validation\Rules\Password;
use App\Broadcasting\SoketiBroadcaster;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 注册自定义 Soketi 广播驱动（零额外 PHP 依赖，Guzzle 直连 Soketi REST）
        // BroadcastServiceProvider 是延迟加载的，这里强制注册以确保 'broadcast' 绑定可用
        if (! $this->app->providerIsLoaded(\Illuminate\Broadcasting\BroadcastServiceProvider::class)) {
            $this->app->register(\Illuminate\Broadcasting\BroadcastServiceProvider::class);
        }

        Broadcast::extend('soketi', function ($app, $config) {
            return new SoketiBroadcaster($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // 自动记录看板活动（驱动复盘仪表盘）
        Card::observe(CardObserver::class);
        Column::observe(ColumnObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
