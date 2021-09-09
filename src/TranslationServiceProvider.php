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
        // first add the lang custom dir because other dependencies need this
        $this->app->instance('chained-translator.path.lang.custom',
                             $this->app->resourcePath(config('laravel-chained-translator.custom_lang_directory_name', 'lang-custom')));
        //create custom language directory and add .gitignore file to avoid commits of customer translations:
        if(!file_exists($this->app->get('chained-translator.path.lang.custom'))) {
            /* @var Filesystem $fileSystem */
            $fileSystem = $this->app->get('files');
            $fileSystem->makeDirectory($this->app->get('chained-translator.path.lang.custom'), 0755, true);
            if(config('laravel-chained-translator.add_gitignore_to_custom_lang_directory', true)) {
                $fileSystem->put($this->app->get('chained-translator.path.lang.custom') . '/.gitignore', "*\n!.gitignore\n");
            }
        }

        // add the parent dependencies
        parent::register();

        // add the chained translation manager who needs parent dependencies
        $this->app->singleton(ChainedTranslationManager::class, function ($app) {
            return new ChainedTranslationManager(
                $app['files'],
                $app['translation.loader'],
                $app['chained-translator.path.lang.custom']
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array_merge(parent::provides(), [
            'chained-translator.path.lang.custom', ChainedTranslationManager::class
        ]);
    }
}
