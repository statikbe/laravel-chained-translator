<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Statikbe\LaravelChainedTranslator\ChainLoader;

describe('framework translations', function () {
    beforeEach(function () {
        // An app lang directory that only overrides a single validation line,
        // like Laravel 11+ apps that no longer ship lang/en/validation.php.
        $this->langPath = createUniqueTempDirectory('chained-translator-lang-');
        $files = new Filesystem();
        $files->makeDirectory($this->langPath . '/en');
        $files->put($this->langPath . '/en/validation.php', "<?php\n\nreturn ['accepted' => 'App accepted.'];\n");

        $this->app->useLangPath($this->langPath);
        $this->app->forgetInstance('translation.loader.default');
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');
    });

    it('uses the chained loader', function () {
        expect(app('translator')->getLoader())->toBeInstanceOf(ChainLoader::class);
    });

    afterEach(function () {
        cleanupTestDirectory($this->langPath);
    });

    it('loads the lines Laravel ships in Illuminate/Translation/lang', function () {
        expect(__('validation.required', ['attribute' => 'name'], 'en'))->toBe('The name field is required.');
    });

    it('lets the app lang directory override framework lines', function () {
        expect(__('validation.accepted', [], 'en'))->toBe('App accepted.');
    });
})->skip(
    !is_dir(
        dirname(
            (string) (new ReflectionClass(\Illuminate\Translation\TranslationServiceProvider::class))->getFileName(),
        )
            . '/lang',
    ),
    'This Laravel version does not ship framework translation files.',
);
