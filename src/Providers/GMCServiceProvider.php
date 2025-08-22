<?php

namespace Mannu24\GMCIntegration\Providers;

use Illuminate\Support\ServiceProvider;
use Mannu24\GMCIntegration\Repositories\GMCRepository;
use Mannu24\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;
use Mannu24\GMCIntegration\Services\GMCService;

class GMCServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/gmc.php', 'gmc');

        $this->app->bind(GMCRepositoryInterface::class, GMCRepository::class);
        
        $this->app->singleton(GMCService::class, function ($app) {
            return new GMCService($app->make(GMCRepositoryInterface::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/gmc.php' => config_path('gmc.php'),
        ], 'gmc-config');

        $this->publishes([
            __DIR__.'/../../database/migrations/create_gmc_products_table.php' => 
                database_path('migrations/2024_01_01_000001_create_gmc_products_table.php'),
            __DIR__.'/../../database/migrations/create_gmc_sync_logs_table.php' => 
                database_path('migrations/2024_01_01_000002_create_gmc_sync_logs_table.php'),
        ], 'gmc-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Mannu24\GMCIntegration\Console\Commands\SyncAllProductsCommand::class,
            ]);
        }
    }
}
