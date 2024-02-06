<?php

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Filesystem\Filesystem;

class TranslationServiceProvider extends BaseTranslationServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        // first add the lang custom dir because other dependencies need this
        $this->app->instance('chained-translator.path.lang.custom', $this->getCustomLangPath());

        //create custom language directory and add .gitignore file to avoid commits of customer translations:
        if (!file_exists($this->app->get('chained-translator.path.lang.custom'))) {
            $this->buildCustomLangDir();
        }

        // add the parent dependencies
        parent::register();

        // add the chained translation manager who needs parent dependencies
        $this->app->singleton(ChainedTranslationManager::class, function ($app) {
            return new ChainedTranslationManager($app['files'], $app['translation.loader'], $app['chained-translator.path.lang.custom']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return array_merge(parent::provides(), [
            'chained-translator.path.lang.custom',
            ChainedTranslationManager::class,
        ]);
    }

    private function getCustomLangPath(): string
    {
        $customLangDirName = config('laravel-chained-translator.custom_lang_directory_name', 'lang-custom');

        if (!file_exists($this->app->basePath($customLangDirName))) {
            if (file_exists($this->app->resourcePath($customLangDirName)) || file_exists($this->app->resourcePath('lang'))) {
                return $this->app->resourcePath($customLangDirName);
            }
        }

        return $this->app->basePath($customLangDirName);
    }

    private function buildCustomLangDir(): void
    {
        /* @var Filesystem $fileSystem */
        $fileSystem = $this->app->get('files');
        $fileSystem->makeDirectory($this->app->get('chained-translator.path.lang.custom'), 0755, true);
        if (config('laravel-chained-translator.add_gitignore_to_custom_lang_directory', true)) {
            $fileSystem->put($this->app->get('chained-translator.path.lang.custom').'/.gitignore', "*\n!.gitignore\n");
        }
    }
}
