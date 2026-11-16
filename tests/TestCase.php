<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator\Tests;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;
use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;
use Statikbe\LaravelChainedTranslator\ChainLoader;
use Statikbe\LaravelChainedTranslator\TranslationServiceProvider;

abstract class TestCase extends Orchestra
{
    /** The app's lang directory, set by useTemporaryLangDirectories(). It does not exist until a file is written. */
    protected string $langPath;

    /** The custom lang directory, set by useTemporaryLangDirectories(). */
    protected string $customLangPath;

    /** A lang directory of a package, set by useTemporaryLangDirectories(). It does not exist until a file is written. */
    protected string $packageLangPath;

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

    /**
     * Points the app's lang directory and the custom lang directory to new temporary directories, which are
     * removed after the test, and makes the translator load from them.
     */
    protected function useTemporaryLangDirectories(): void
    {
        $tempPath = createUniqueTempDirectory('chained-translator-');
        $this->langPath = $tempPath . '/lang';
        $this->customLangPath = $tempPath . '/lang-custom';
        $this->packageLangPath = $tempPath . '/package-lang';
        mkdir($this->customLangPath);

        $this->app->useLangPath($this->langPath);
        $this->app->instance('chained-translator.path.lang.custom', $this->customLangPath);

        // Resolved again from the paths above when the translator is next used.
        $this->app->forgetInstance('translation.loader.default');
        $this->app->forgetInstance('translation.loader.custom');
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');

        $this->beforeApplicationDestroyed(static fn() => cleanupTestDirectory($tempPath));
    }

    /**
     * A translation manager on the custom lang directory, using the translator's loaders.
     */
    protected function translationManager(): ChainedTranslationManager
    {
        return new ChainedTranslationManager(
            new Filesystem(),
            $this->app->make(ChainLoader::class),
            $this->customLangPath,
            new ChainedTranslatorConfig(),
        );
    }

    /**
     * Writes a translation file, creating its directory. Lines are written as a PHP file, a string as is.
     *
     * @param array<string, mixed>|string $contents
     */
    protected function writeLangFile(string $path, array|string $contents): void
    {
        $files = new Filesystem();
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, is_array($contents) ? '<?php return ' . var_export($contents, true) . ';' : $contents);
    }

    /**
     * Reads the lines of a PHP or JSON translation file.
     *
     * @return array<string, mixed>
     */
    protected function readLangFile(string $path): array
    {
        $files = new Filesystem();

        /** @var array<string, mixed> */
        return str_ends_with($path, '.json') ? json_decode($files->get($path), true) : $files->getRequire($path);
    }
}
