<?php

namespace BeyondCode\LaravelWebSockets;

use BeyondCode\LaravelWebSockets\Apps\AppProvider;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\AuthenticateDashboard;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\DashboardApiController;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\SendMessage;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\ShowDashboard;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Middleware\Authorize as AuthorizeDashboard;
use BeyondCode\LaravelWebSockets\Server\Router;
use BeyondCode\LaravelWebSockets\Statistics\Http\Controllers\WebSocketStatisticsEntriesController;
use BeyondCode\LaravelWebSockets\Statistics\Http\Middleware\Authorize as AuthorizeStatistics;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManagers\ArrayChannelManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WebSocketsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/websockets.php' => base_path('config/websockets.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/0000_00_00_000000_create_websockets_statistics_entries_table.php' => database_path('migrations/0000_00_00_000000_create_websockets_statistics_entries_table.php'),
        ], 'migrations');

        $this
            ->registerRoutes()
            ->registerDashboardGate();

        $this->loadViewsFrom(__DIR__.'/../resources/views/', 'websockets');

        $this->commands([
            Console\StartWebSocketServer::class,
            Console\CleanStatistics::class,
            Console\RestartWebSocketServer::class,
        ]);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/websockets.php', 'websockets');

        $this->app->singleton('websockets.router', function () {
            return new Router();
        });

        $this->app->singleton(ChannelManager::class, function () {
            return config('websockets.channel_manager') !== null && class_exists(config('websockets.channel_manager'))
                ? app(config('websockets.channel_manager')) : new ArrayChannelManager();
        });

        $this->app->singleton(AppProvider::class, function () {
            return app(config('websockets.app_provider'));
        });
    }

    protected function registerRoutes()
    {
        app('router')->group([
            'prefix' => config('websockets.path'),
            'middleware' => AuthorizeDashboard::class,
        ], function () {
            app('router')->get('/', ShowDashboard::class);
            app('router')->get('/api/{appId}/statistics', [DashboardApiController::class, 'getStatistics', ]);
            app('router')->post('auth', AuthenticateDashboard::class);
            app('router')->post('event', SendMessage::class);

            app('router')->group(['middleware' => AuthorizeStatistics::class], function () {
                app('router')->post('statistics', [WebSocketStatisticsEntriesController::class, 'store', ]);
            });
        });
        return $this;
    }

    protected function registerDashboardGate()
    {
        Gate::define('viewWebSocketsDashboard', function ($user = null) {
            return app()->environment('local');
        });

        return $this;
    }
}
