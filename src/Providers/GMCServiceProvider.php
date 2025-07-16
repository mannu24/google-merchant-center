<?php

namespace Manu\GMCIntegration\Providers;

use Illuminate\Support\ServiceProvider;
use Manu\GMCIntegration\Repositories\GMCRepository;
use Manu\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;
use Manu\GMCIntegration\Repositories\ProductRepository;
use Manu\GMCIntegration\Repositories\Interfaces\ProductRepositoryInterface;
use Manu\GMCIntegration\Services\GMCService;

class GMCServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../../config/gmc.php' => config_path('gmc.php'),
        ], 'gmc-config');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Manu\GMCIntegration\Console\Commands\SyncAllProductsCommand::class,
            ]);
        }
    }

    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../../config/gmc.php', 'gmc');

        // Bind repositories
        $this->app->bind(GMCRepositoryInterface::class, GMCRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        
        // Bind service
        $this->app->singleton(GMCService::class, function ($app) {
            return new GMCService($app->make(GMCRepositoryInterface::class));
        });
    }
}
