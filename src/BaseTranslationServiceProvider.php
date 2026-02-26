<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader as TranslationLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\TranslationServiceProvider as LaravelTranslationServiceProvider;
use Statikbe\LaravelChainedTranslator\Console\Commands\MergeTranslationsCommand;

class BaseTranslationServiceProvider extends LaravelTranslationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //publish config:
        $this->publishes([
            __DIR__ . '/../config/laravel-chained-translator.php' => config_path('laravel-chained-translator.php'),
        ], 'config');

        //register commands:
        if ($this->app->runningInConsole()) {
            $this->commands([
                MergeTranslationsCommand::class,
            ]);
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        //merge config:
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-chained-translator.php', 'laravel-chained-translator');

        parent::register();
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader(): void
    {
        $this->app->singleton('translation.loader.default', static function ($app) {
            assert($app instanceof Application, 'App must be an instance of Application.');
            $files = $app->make(Filesystem::class);
            $langPath = $app->langPath();

            return new FileLoader($files, $langPath);
        });

        $this->app->singleton('translation.loader.custom', static function ($app) {
            assert($app instanceof Application, 'App must be an instance of Application.');
            $files = $app->make(Filesystem::class);
            // Use the container binding for custom path resolution
            $customPath = $app->get('chained-translator.path.lang.custom');

            return new NonPackageFileLoader($files, $customPath);
        });

        //override the Laravel translation loader singleton:
        $this->app->singleton('translation.loader', static function ($app) {
            assert($app instanceof Application, 'App must be an instance of Application.');
            $loader = new ChainLoader();
            $customLoader = $app->make('translation.loader.custom');
            assert($customLoader instanceof TranslationLoader, 'Custom loader must implement TranslationLoader.');
            $defaultLoader = $app->make('translation.loader.default');
            assert($defaultLoader instanceof TranslationLoader, 'Default loader must implement TranslationLoader.');
            $loader->addLoader($customLoader);
            $loader->addLoader($defaultLoader);

            return $loader;
        });

        //added here to make sure when we inject the class name in a constructor this singleton is used:
        $this->app->alias('translation.loader', ChainLoader::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        /** @var array<int, string> $parentProvides */
        $parentProvides = parent::provides();

        return array_merge($parentProvides, [
            'translation.loader.custom',
            'translation.loader.default',
            'translation.manager',
        ]);
    }
}
