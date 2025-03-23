<?php

namespace Manu\GMCIntegration\Providers;

use Illuminate\Support\ServiceProvider;
use Manu\GMCIntegration\Repositories\GMCRepository;
use Manu\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;

class GMCServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(GMCRepositoryInterface::class, GMCRepository::class);
    }

    public function boot()
    {
        //
    }
}
