<?php

declare(strict_types=1);

namespace {{ namespace }}\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use {{ namespace }}\Providers\{{ plugin }}ServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param Application $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            {{ plugin }}ServiceProvider::class,
        ];
    }
}
