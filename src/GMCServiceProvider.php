<?php

namespace Manu\GMCIntegration;

use Illuminate\Support\ServiceProvider;
use Manu\GMCIntegration\Repositories\ProductRepository;
use Manu\GMCIntegration\Repositories\Interfaces\ProductRepositoryInterface;

class GMCServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/gmc.php' => config_path('gmc.php'),
        ], 'config');
    }

    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/gmc.php', 'gmc');

        // Bind the repository
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
    }
}
