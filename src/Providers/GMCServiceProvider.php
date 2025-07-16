<?php

namespace Manu\GMCIntegration\Providers;

use Illuminate\Support\ServiceProvider;
use Manu\GMCIntegration\Repositories\GMCRepository;
use Manu\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;
use Manu\GMCIntegration\Services\GMCService;

class GMCServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/gmc.php', 'gmc');

        $this->app->bind(GMCRepositoryInterface::class, GMCRepository::class);
        
        $this->app->singleton(GMCService::class, function ($app) {
            return new GMCService($app->make(GMCRepositoryInterface::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../../config/gmc.php' => config_path('gmc.php'),
        ], 'gmc-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations/create_gmc_products_table.php' => 
                database_path('migrations/2024_01_01_000001_create_gmc_products_table.php'),
            __DIR__.'/../../database/migrations/create_gmc_sync_logs_table.php' => 
                database_path('migrations/2024_01_01_000002_create_gmc_sync_logs_table.php'),
        ], 'gmc-migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Manu\GMCIntegration\Console\Commands\SyncAllProductsCommand::class,
            ]);
        }
    }
}
