<?php

declare(strict_types=1);

use Statikbe\LaravelChainedTranslator\TranslationServiceProvider;

/**
 * The service provider picks and creates the custom lang directory when it registers.
 */
describe('custom lang directory', function () {
    beforeEach(function () {
        $this->basePath = createUniqueTempDirectory('chained-translator-base-');
        $this->app->setBasePath($this->basePath);

        $this->register = function (): string {
            (new TranslationServiceProvider($this->app))->register();

            return (string) $this->app->make('chained-translator.path.lang.custom');
        };
    });

    afterEach(function () {
        cleanupTestDirectory($this->basePath);
    });

    it('creates the directory in the base path with a .gitignore', function () {
        expect(($this->register)())->toBe($this->basePath . '/lang-custom');
        expect(file_get_contents($this->basePath . '/lang-custom/.gitignore'))->toBe("*\n!.gitignore\n");
    });

    it('does not add a .gitignore when disabled in the config', function () {
        config()->set('laravel-chained-translator.add_gitignore_to_custom_lang_directory', false);

        ($this->register)();

        expect($this->basePath . '/lang-custom')->toBeDirectory();
        expect($this->basePath . '/lang-custom/.gitignore')->not->toBeFile();
    });

    it('uses the directory name from the config', function () {
        config()->set('laravel-chained-translator.custom_lang_directory_name', 'translations-custom');

        expect(($this->register)())->toBe($this->basePath . '/translations-custom');
        expect($this->basePath . '/translations-custom')->toBeDirectory();
    });

    it('creates the directory next to resources/lang in apps that still use it', function () {
        mkdir($this->basePath . '/resources/lang', 0o755, true);

        expect(($this->register)())->toBe($this->basePath . '/resources/lang-custom');
        expect($this->basePath . '/resources/lang-custom')->toBeDirectory();
        expect($this->basePath . '/lang-custom')->not->toBeDirectory();
    });

    it('keeps using an existing directory in the base path', function () {
        mkdir($this->basePath . '/resources/lang', 0o755, true);
        mkdir($this->basePath . '/lang-custom');

        expect(($this->register)())->toBe($this->basePath . '/lang-custom');
        expect($this->basePath . '/lang-custom/.gitignore')->not->toBeFile();
    });
});
