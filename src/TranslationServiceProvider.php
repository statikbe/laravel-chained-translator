<?php

declare(strict_types=1);

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

        // create custom language directory and add .gitignore file to avoid commits of customer translations:
        if (!file_exists($this->getCustomLangPath())) {
            $this->buildCustomLangDir();
        }

        // add the parent dependencies
        parent::register();

        // add the chained translation manager who needs parent dependencies
        $this->app->singleton(ChainedTranslationManager::class, function ($app) {
            assert(
                $app instanceof \Illuminate\Contracts\Foundation\Application,
                'App must be an instance of Application.',
            );
            $files = $app->make(Filesystem::class);
            $loader = $app->make(ChainLoader::class);
            $path = $this->getCustomLangPath();

            return new ChainedTranslationManager($files, $loader, $path);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
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
        /** @var string $customLangDirName */
        $customLangDirName = config('laravel-chained-translator.custom_lang_directory_name', 'lang-custom');

        if (!file_exists($this->app->basePath($customLangDirName))) {
            if (
                file_exists($this->app->resourcePath($customLangDirName))
                || file_exists($this->app->resourcePath('lang'))
            ) {
                return $this->app->resourcePath($customLangDirName);
            }
        }

        return $this->app->basePath($customLangDirName);
    }

    private function buildCustomLangDir(): void
    {
        $fileSystem = $this->app->make(Filesystem::class);
        $customLangPath = $this->getCustomLangPath();
        $fileSystem->makeDirectory($customLangPath, 0o755, true);
        if (config('laravel-chained-translator.add_gitignore_to_custom_lang_directory', true)) {
            $fileSystem->put($customLangPath . '/.gitignore', "*\n!.gitignore\n");
        }
    }
}
