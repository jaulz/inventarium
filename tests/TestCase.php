<?php

namespace Jaulz\Inventarium\Tests;

use Jaulz\Inventarium\InventariumServiceProvider;
use Tpetry\PostgresqlEnhanced\PostgresqlEnhancedServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            InventariumServiceProvider::class,
            PostgresqlEnhancedServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app) {
    }
}