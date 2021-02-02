<?php


    namespace Statikbe\LaravelChainedTranslator;

    use Illuminate\Translation\FileLoader;
    use Illuminate\Translation\TranslationServiceProvider as LaravelTranslationServiceProvider;
    use Statikbe\LaravelChainedTranslator\Console\Commands\MergeTranslationsCommand;

    class BaseTranslationServiceProvider extends LaravelTranslationServiceProvider {

        /**
         * Bootstrap any application services.
         *
         * @return void
         */
        public function boot()
        {
            //publish config:
            $this->publishes([
                __DIR__.'/../config/laravel-chained-translator.php' => config_path('laravel-chained-translator.php'),
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
        public function register() {
            //merge config:
            $this->mergeConfigFrom(
                __DIR__.'/../config/laravel-chained-translator.php', 'laravel-chained-translator'
            );

            //load helpers:
            $file = __DIR__ . '/Helpers/helpers.php';
            if(file_exists($file)) {
                require_once($file);
            }

            parent::register();
        }

        /**
         * Register the translation line loader.
         *
         * @return void
         */
        protected function registerLoader()
        {
            $this->app->singleton('translation.loader.default', function ($app) {
                return new FileLoader($app['files'], $app['path.lang']);
            });
            $this->app->singleton('translation.loader.custom', function ($app) {
                return new FileLoader($app['files'], $app['chained-translator.path.lang.custom']);
            });
            //override the Laravel translation loader singleton:
            $this->app->singleton('translation.loader', function ($app) {
                $loader = new ChainLoader();
                $loader->addLoader($app['translation.loader.custom']);
                $loader->addLoader($app['translation.loader.default']);

                return $loader;
            });

            //added here to make sure when we inject the class name in a constructor this singleton is used:
            $this->app->singleton(ChainLoader::class, function ($app) {
                $loader = new ChainLoader();
                $loader->addLoader($app['translation.loader.custom']);
                $loader->addLoader($app['translation.loader.default']);

                return $loader;
            });
        }

        /**
         * Get the services provided by the provider.
         *
         * @return array
         */
        public function provides()
        {
            $provides = parent::provides();

            $provides[] = 'translation.loader.custom';
            $provides[] = 'translation.loader.default';
            $provides[] = 'translation.manager';

            return $provides;
        }
    }
