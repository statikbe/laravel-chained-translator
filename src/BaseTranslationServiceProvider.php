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
     * The package name used for config, service container bindings, etc.
     */
    public const NAME = 'laravel-chained-translator';

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //publish config:
        $this->publishes([
            __DIR__ . '/../config/' . self::NAME . '.php' => config_path(self::NAME . '.php'),
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
        $this->mergeConfigFrom(__DIR__ . '/../config/' . self::NAME . '.php', self::NAME);

        parent::register();
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader(): void
    {
        $this->app->singleton('translation.loader.default', static function (Application $app) {
            $files = $app->make(Filesystem::class);

            // Same paths, same order as Laravel's own translation loader: the framework's lines first,
            // then the app's lang directory, which overrides them line by line.
            $paths = array_values(array_filter([FrameworkLangPath::resolve(), $app->langPath()]));

            return new FileLoader($files, $paths);
        });

        $this->app->singleton('translation.loader.custom', static function (Application $app) {
            $files = $app->make(Filesystem::class);
            // Use the container binding for custom path resolution
            $customPath = (string) $app->get('chained-translator.path.lang.custom');

            return new NonPackageFileLoader($files, $customPath);
        });

        //override the Laravel translation loader singleton:
        $this->app->singleton('translation.loader', static function (Application $app) {
            $loader = new ChainLoader();
            /** @var TranslationLoader $customLoader */
            $customLoader = $app->make('translation.loader.custom');
            /** @var TranslationLoader $defaultLoader */
            $defaultLoader = $app->make('translation.loader.default');
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
