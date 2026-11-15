<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Statikbe\LaravelChainedTranslator\TranslationServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TranslationServiceProvider::class,
        ];
    }
}
