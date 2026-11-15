<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;
use Statikbe\LaravelChainedTranslator\ChainLoader;

/**
 * Merging custom translations into lang/ for groups Laravel ships itself (validation, auth, ...).
 *
 * Since Laravel 11 apps only keep the lines they override in lang/; the rest comes from
 * Illuminate/Translation/lang. Merging must keep it that way: write the app's own lines plus the
 * custom lines, never copies of the framework lines.
 */
describe('merging framework translation groups', function () {
    beforeEach(function () {
        $this->files = new Filesystem();
        $this->langPath = createUniqueTempDirectory('chained-translator-lang-');
        $this->customPath = createUniqueTempDirectory('chained-translator-lang-custom-');

        $this->app->useLangPath($this->langPath);
        $this->app->instance('chained-translator.path.lang.custom', $this->customPath);
        $this->app->forgetInstance('translation.loader.default');
        $this->app->forgetInstance('translation.loader.custom');
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');

        $this->manager = new ChainedTranslationManager(
            $this->files,
            $this->app->make(ChainLoader::class),
            $this->customPath,
            new ChainedTranslatorConfig(),
        );

        $this->writeLangFile = function (string $path, array $lines): void {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, '<?php return ' . var_export($lines, true) . ';');
        };

        $this->readLangFile = fn(string $path): array => require $path;
    });

    afterEach(function () {
        cleanupTestDirectory($this->langPath);
        cleanupTestDirectory($this->customPath);
    });

    it('lists framework groups the app does not override', function () {
        expect($this->manager->getTranslationGroups())->toContain('validation');
    });

    it('merges an edit into an override-only file without copying framework lines', function () {
        ($this->writeLangFile)($this->langPath . '/en/validation.php', ['accepted' => 'App accepted.']);
        $this->manager->save('en', 'validation', 'required', 'Custom required.');

        $this->manager->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect(($this->readLangFile)($this->langPath . '/en/validation.php'))->toBe([
            'accepted' => 'App accepted.',
            'required' => 'Custom required.',
        ]);
    });

    it('writes only the edited lines when another locale has the group', function () {
        ($this->writeLangFile)($this->langPath . '/nl/validation.php', ['required' => 'Het veld is verplicht.']);
        $this->manager->save('en', 'validation', 'required', 'Custom required.');

        $this->manager->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect(($this->readLangFile)($this->langPath . '/en/validation.php'))->toBe([
            'required' => 'Custom required.',
        ]);
    });

    it('merges an edit of a group that only the framework ships', function () {
        $this->files->ensureDirectoryExists($this->langPath . '/en');
        $this->manager->save('en', 'validation', 'required', 'Custom required.');

        $this->manager->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->langPath . '/en/validation.php')->toBeFile();
        expect(($this->readLangFile)($this->langPath . '/en/validation.php'))->toBe([
            'required' => 'Custom required.',
        ]);
    });

    it('does not create files for framework groups nobody edited', function () {
        $this->files->ensureDirectoryExists($this->langPath . '/en');
        $this->manager->save('en', 'validation', 'required', 'Custom required.');

        $this->manager->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->langPath . '/en/auth.php')->not->toBeFile();
        expect($this->langPath . '/en/pagination.php')->not->toBeFile();
        expect($this->langPath . '/en/passwords.php')->not->toBeFile();
    });

    it('applies an edit of a framework-only group at runtime', function () {
        $this->files->ensureDirectoryExists($this->langPath . '/en');
        $this->manager->save('en', 'validation', 'required', 'Custom required.');

        expect(__('validation.required', [], 'en'))->toBe('Custom required.');
        expect(__('validation.accepted', ['attribute' => 'terms'], 'en'))->toBe('The terms field must be accepted.');
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
