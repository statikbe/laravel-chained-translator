<?php

declare(strict_types=1);

/**
 * Merging the custom translations into the app's lang directory (chainedtranslator:merge).
 */
beforeEach(function () {
    $this->useTemporaryLangDirectories();
});

describe('framework groups', function () {
    // Since Laravel 11 apps only keep the lines they override in lang/; the rest comes from
    // Illuminate/Translation/lang. Merging must keep it that way: write the app's own lines plus the
    // custom lines, never copies of the framework lines.

    it('merges an edit into an override-only file without copying framework lines', function () {
        $this->writeLangFile($this->langPath . '/en/validation.php', ['accepted' => 'App accepted.']);
        $this->translationManager()->save('en', 'validation', 'required', 'Custom required.');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/en/validation.php'))->toBe([
            'accepted' => 'App accepted.',
            'required' => 'Custom required.',
        ]);
    });

    it('writes only the edited lines when another locale has the group', function () {
        $this->writeLangFile($this->langPath . '/nl/validation.php', ['required' => 'Het veld is verplicht.']);
        $this->translationManager()->save('en', 'validation', 'required', 'Custom required.');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/en/validation.php'))->toBe([
            'required' => 'Custom required.',
        ]);
    });

    it('merges an edit of a group that only the framework ships', function () {
        $this->translationManager()->save('en', 'validation', 'required', 'Custom required.');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/en/validation.php'))->toBe([
            'required' => 'Custom required.',
        ]);
    });

    it('does not create files for framework groups nobody edited', function () {
        $this->translationManager()->save('en', 'validation', 'required', 'Custom required.');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->langPath . '/en/auth.php')->not->toBeFile();
        expect($this->langPath . '/en/pagination.php')->not->toBeFile();
        expect($this->langPath . '/en/passwords.php')->not->toBeFile();
    });
});

describe('JSON translations', function () {
    it('merges custom JSON translations into the app JSON file', function () {
        $this->writeLangFile($this->langPath . '/en.json', '{"Hello": "App hello", "Bye": "App bye"}');
        $this->translationManager()->save('en', 'json-file', 'Hello', 'Custom hello');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/en.json'))->toBe([
            'Bye' => 'App bye',
            'Hello' => 'Custom hello',
        ]);
    });

    it('merges custom JSON translations into an app without a lang directory', function () {
        $this->translationManager()->save('en', 'json-file', 'Hello', 'Hallo');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/en.json'))->toBe(['Hello' => 'Hallo']);
    });

    it('keeps JSON keys with dots as they are', function () {
        $this->translationManager()->save('en', 'json-file', 'Saved. Thanks.', 'Opgeslagen. Bedankt.');
        $this->translationManager()->save('en', 'json-file', 'menu.home', 'Home');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/en.json'))->toBe([
            'Saved. Thanks.' => 'Opgeslagen. Bedankt.',
            'menu.home' => 'Home',
        ]);
    });
});

describe('dotted keys', function () {
    // Custom lines are saved with dotted keys, app files are usually nested. The merged file in lang/
    // must hold each line once, nested, or the dotted key hides later edits of the nested line.

    it('nests a custom line into the app file', function (bool $groupKeysInArray) {
        config()->set('laravel-chained-translator.group_keys_in_array', $groupKeysInArray);
        $this->writeLangFile($this->langPath . '/en/messages.php', [
            'between' => ['numeric' => 'App numeric', 'string' => 'App string'],
        ]);
        $this->translationManager()->save('en', 'messages', 'between.numeric', 'Custom numeric');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/en/messages.php'))->toBe([
            'between' => ['numeric' => 'Custom numeric', 'string' => 'App string'],
        ]);
    })->with([
        'keys grouped in arrays' => [true],
        'dotted keys' => [false],
    ]);

    it('folds dotted keys from earlier merges into the nested lines', function () {
        $this->writeLangFile($this->langPath . '/en/messages.php', [
            'between' => ['numeric' => 'App numeric', 'string' => 'App string'],
            'between.string' => 'Earlier merged string',
        ]);
        $this->translationManager()->save('en', 'messages', 'between.numeric', 'Custom numeric');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/en/messages.php'))->toBe([
            'between' => ['numeric' => 'Custom numeric', 'string' => 'Earlier merged string'],
        ]);
    });
});

describe('keys with dots', function () {
    // Keys like 'items.*.name' contain dots themselves. Custom lines must be merged into them, not into a
    // second, nested copy such as 'items' => ['*' => ['name' => ...]].

    it('merges an edited wildcard message into the existing key', function (bool $groupKeysInArray) {
        config()->set('laravel-chained-translator.group_keys_in_array', $groupKeysInArray);
        $this->writeLangFile($this->langPath . '/en/validation.php', [
            'custom' => ['items.*.name' => ['required' => 'App message.', 'max' => 'App max message.']],
        ]);
        $this->translationManager()->save('en', 'validation', 'custom.items.*.name.required', 'Custom message.');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/en/validation.php'))->toBe([
            'custom' => ['items.*.name' => ['required' => 'Custom message.', 'max' => 'App max message.']],
        ]);
    })->with([
        'keys grouped in arrays' => [true],
        'dotted keys' => [false],
    ]);
});

describe('package groups', function () {
    beforeEach(function () {
        app('translator')->addNamespace('package', $this->packageLangPath);
    });

    it('copies all lines of a package group into the app vendor file', function () {
        // Unlike plain groups, package groups are not merged override-only: the package's own lines
        // are written to lang/vendor/ as well.
        $this->writeLangFile($this->packageLangPath . '/en/messages.php', [
            'hello' => 'Package hello',
            'bye' => 'Package bye',
            'welcome' => 'Package welcome',
        ]);
        $this->writeLangFile($this->langPath . '/vendor/package/en/messages.php', ['hello' => 'App hello']);
        $this->translationManager()->save('en', 'package::messages', 'bye', 'Custom bye');

        expect(__('package::messages.bye', [], 'en'))->toBe('Custom bye');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/vendor/package/en/messages.php'))->toBe([
            'bye' => 'Custom bye',
            'hello' => 'App hello',
            'welcome' => 'Package welcome',
        ]);
    });

    it('nests custom lines in package groups', function () {
        $this->writeLangFile($this->packageLangPath . '/en/messages.php', [
            'greeting' => ['hello' => 'Package hello', 'bye' => 'Package bye'],
        ]);
        $this->writeLangFile($this->langPath . '/vendor/package/en/messages.php', [
            'greeting' => ['hello' => 'App hello'],
        ]);
        $this->translationManager()->save('en', 'package::messages', 'greeting.bye', 'Custom bye');

        $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('en');

        expect($this->readLangFile($this->langPath . '/vendor/package/en/messages.php'))->toEqual([
            'greeting' => ['hello' => 'App hello', 'bye' => 'Custom bye'],
        ]);
    });
});

it('does not create a locale directory in the custom lang directory', function () {
    $this->writeLangFile($this->langPath . '/de/messages.php', ['hello' => 'Hallo']);

    $this->translationManager()->mergeChainedTranslationsIntoDefaultTranslations('de');

    expect($this->customLangPath . '/de')->not->toBeDirectory();
});
