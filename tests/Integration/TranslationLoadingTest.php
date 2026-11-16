<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Statikbe\LaravelChainedTranslator\ChainLoader;

/**
 * Loading and saving translations through the loaders the service provider registers: the lines Laravel
 * ships, the app's lang directory, the custom lang directory and paths that packages add.
 */
beforeEach(function () {
    $this->useTemporaryLangDirectories();
});

it('uses the chained loader', function () {
    expect(app('translator')->getLoader())->toBeInstanceOf(ChainLoader::class);
});

describe('framework translations', function () {
    // Since Laravel 11 the framework ships its default lines in Illuminate/Translation/lang, and apps only
    // keep the lines they override in lang/.

    it('loads the lines Laravel ships', function () {
        expect(__('validation.required', ['attribute' => 'name'], 'en'))->toBe('The name field is required.');
    });

    it('lets the app lang directory override framework lines', function () {
        $this->writeLangFile($this->langPath . '/en/validation.php', ['accepted' => 'App accepted.']);

        expect(__('validation.accepted', [], 'en'))->toBe('App accepted.');
        expect(__('validation.required', ['attribute' => 'name'], 'en'))->toBe('The name field is required.');
    });

    it('lets custom lines override framework lines', function () {
        $this->translationManager()->save('en', 'validation', 'required', 'Custom required.');

        expect(__('validation.required', [], 'en'))->toBe('Custom required.');
        expect(__('validation.accepted', ['attribute' => 'terms'], 'en'))->toBe('The terms field must be accepted.');
    });

    it('lists framework groups the app does not override', function () {
        expect($this->translationManager()->getTranslationGroups())->toContain('validation');
    });
});

describe('without a lang directory', function () {
    // Laravel 11+ apps have no lang directory until translations are published.

    it('lists the translation groups', function () {
        expect($this->translationManager()->getTranslationGroups())->toBeArray();
    });
});

describe('paths added by packages', function () {
    it('loads groups from a path added with Translator::addPath()', function () {
        $this->writeLangFile($this->packageLangPath . '/en/package.php', ['line' => 'Package line.']);

        app('translator')->addPath($this->packageLangPath);

        expect(__('package.line', [], 'en'))->toBe('Package line.');
        expect(app(ChainLoader::class)->paths())->toContain($this->packageLangPath);
    });

    it('lets custom translations override a path added with Translator::addPath()', function () {
        $this->writeLangFile($this->packageLangPath . '/en/package.php', ['line' => 'Package line.']);
        $this->writeLangFile($this->customLangPath . '/en/package.php', ['line' => 'Custom line.']);

        app('translator')->addPath($this->packageLangPath);

        expect(__('package.line', [], 'en'))->toBe('Custom line.');
    });

    it('lets the app lang directory override JSON paths added by packages', function () {
        $this->writeLangFile($this->langPath . '/en.json', '{"Hello": "App hello"}');
        $this->writeLangFile($this->packageLangPath . '/en.json', '{"Hello": "Package hello", "Bye": "Package bye"}');

        app('translator')->addJsonPath($this->packageLangPath);

        expect(__('Hello', [], 'en'))->toBe('App hello');
        expect(__('Bye', [], 'en'))->toBe('Package bye');
    });
});

describe('saving and reading', function () {
    it('returns the custom lines over the app lines in dot notation', function () {
        $this->writeLangFile($this->langPath . '/en/messages.php', [
            'greeting' => ['hello' => 'App hello', 'bye' => 'App bye'],
        ]);
        $this->translationManager()->save('en', 'messages', 'greeting.hello', 'Custom hello');

        expect($this->translationManager()->getTranslationsForGroup('en', 'messages'))->toEqualCanonicalizing([
            'greeting.hello' => 'Custom hello',
            'greeting.bye' => 'App bye',
        ]);
        expect(__('messages.greeting.hello', [], 'en'))->toBe('Custom hello');
    });

    it('adds to and updates an existing nested custom file when keys are grouped in arrays', function () {
        config()->set('laravel-chained-translator.group_keys_in_array', true);
        $this->writeLangFile($this->customLangPath . '/en/messages.php', ['greeting' => ['hello' => 'Old hello']]);

        $this->translationManager()->save('en', 'messages', 'greeting.hello', 'New hello');
        $this->translationManager()->save('en', 'messages', 'greeting.bye', 'New bye');

        // toEqual: the writer only sorts the top-level keys.
        expect($this->readLangFile($this->customLangPath . '/en/messages.php'))->toEqual([
            'greeting' => ['bye' => 'New bye', 'hello' => 'New hello'],
        ]);
    });

    it('keeps dotted keys flat when keys are not grouped in arrays', function () {
        config()->set('laravel-chained-translator.group_keys_in_array', false);

        $this->translationManager()->save('en', 'messages', 'greeting.hello', 'Hello');

        expect($this->readLangFile($this->customLangPath . '/en/messages.php'))->toBe([
            'greeting.hello' => 'Hello',
        ]);
    });
});

describe('lines read as arrays', function () {
    // The validator reads validation.attributes and validation.custom as whole arrays. Custom lines are saved
    // with dotted keys, so they must be placed inside these arrays, or the validator does not see them.

    beforeEach(function () {
        $this->writeLangFile($this->langPath . '/en/validation.php', [
            'attributes' => ['email' => 'App e-mail', 'name' => 'App name'],
            'custom' => ['items.*.name' => ['required' => 'App item name message.']],
        ]);
        $this->validationErrors = fn(): array => Validator::make(['email' => '', 'items' => [['name' => '']]], [
            'email' => 'required',
            'items.*.name' => 'required',
        ])->errors()->all();
    });

    it('uses an edited attribute name in validation messages', function (bool $groupKeysInArray) {
        config()->set('laravel-chained-translator.group_keys_in_array', $groupKeysInArray);
        $this->translationManager()->save('en', 'validation', 'attributes.email', 'Custom e-mail');

        expect(($this->validationErrors)())->toContain('The Custom e-mail field is required.');
        expect(__('validation.attributes', [], 'en'))->toBe(['email' => 'Custom e-mail', 'name' => 'App name']);
    })->with([
        'keys grouped in arrays' => [true],
        'dotted keys' => [false],
    ]);

    it('uses an edited custom message for a wildcard field', function (bool $groupKeysInArray) {
        config()->set('laravel-chained-translator.group_keys_in_array', $groupKeysInArray);
        $this->translationManager()->save(
            'en',
            'validation',
            'custom.items.*.name.required',
            'Custom item name message.',
        );

        expect(($this->validationErrors)())->toContain('Custom item name message.');
        expect(__('validation.custom', [], 'en'))->toBe([
            // The placeholder in Laravel's own validation.php.
            'attribute-name' => ['rule-name' => 'custom-message'],
            'items.*.name' => ['required' => 'Custom item name message.'],
        ]);
    })->with([
        'keys grouped in arrays' => [true],
        'dotted keys' => [false],
    ]);

    it('lists an edited wildcard message once', function () {
        $this->translationManager()->save(
            'en',
            'validation',
            'custom.items.*.name.required',
            'Custom item name message.',
        );

        $translations = $this->translationManager()->getTranslationsForGroup('en', 'validation');

        expect($translations['custom.items.*.name.required'])->toBe('Custom item name message.');
        expect(array_keys($translations))->toHaveCount(count(array_unique(array_keys($translations))));
        expect($translations)->not->toHaveKey('custom.items.*.name');
    });
});

describe('concurrent saves', function () {
    it('waits for a save in another process and keeps its edit', function () {
        // Another process takes the save lock, adds a line to the custom file after a moment, and releases
        // the lock. A save started meanwhile must wait, and then read the file with that line in it.
        $file = $this->customLangPath . '/en/messages.php';
        $this->writeLangFile($file, ['first' => 'First']);
        $script = sprintf(
            '$lock = fopen(%s, "c"); flock($lock, LOCK_EX); echo "locked\n"; usleep(500000); '
            . 'file_put_contents(%s, "<?php return [\'first\' => \'First\', \'other\' => \'Other\'];"); flock($lock, LOCK_UN);',
            var_export($this->customLangPath . '/.lock', true),
            var_export($file, true),
        );
        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w']], $pipes);
        expect(fgets($pipes[1]))->toBe("locked\n");

        $this->translationManager()->save('en', 'messages', 'mine', 'Mine');

        fclose($pipes[1]);
        proc_close($process);
        expect($this->readLangFile($file))->toBe(['first' => 'First', 'mine' => 'Mine', 'other' => 'Other']);
    });
});
