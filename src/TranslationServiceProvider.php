<?php

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Filesystem\Filesystem;


class TranslationServiceProvider extends BaseTranslationServiceProvider {
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        parent::register();

        $this->app->instance('chained-translator.path.lang.custom',
                             $this->app->resourcePath(config('laravel-chained-translator.custom_lang_directory_name', 'lang-custom')));
        //create custom language directory and add .gitignore file to avoid commits of customer translations:
        if(!file_exists($this->app->get('chained-translator.path.lang.custom'))) {
            /* @var Filesystem $fileSystem */
            $fileSystem = $this->app->get('files');
            $fileSystem->makeDirectory($this->app->get('chained-translator.path.lang.custom'), 0755, true);
            if(config('laravel-chained-translator.add_gitignore_to_custom_lang_directory', true)) {
                touch($this->app->get('chained-translator.path.lang.custom') . '/.gitignore');
            }
        }

        $this->app->singleton('chained-translator.manager', function ($app) {
            return new ChainedTranslationManager($app['files'],
                $app['translation.loader'],
                $app['chained-translator.path.lang.custom']);
        });
    }
}
