<?php

namespace Mannu24\GMCIntegration\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Mannu24\GMCIntegration\Providers\GMCServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [GMCServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        
        $migration = include __DIR__.'/../database/migrations/create_gmc_products_table.php';
        $migration->up();
        
        $migration2 = include __DIR__.'/../database/migrations/create_gmc_sync_logs_table.php';
        $migration2->up();
    }
} 