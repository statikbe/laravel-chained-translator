<?php

declare(strict_types=1);

use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;

describe('ChainedTranslatorConfig', function () {
    beforeEach(function () {
        // Clear config before each test
        config()->set('laravel-chained-translator', null);
    });

    it('returns default custom lang directory name', function () {
        $config = new ChainedTranslatorConfig();

        expect($config->getCustomLangDirectoryName())->toBe('lang-custom');
    });

    it('returns custom lang directory name from config', function () {
        config()->set('laravel-chained-translator.custom_lang_directory_name', 'custom-lang');
        $config = new ChainedTranslatorConfig();

        expect($config->getCustomLangDirectoryName())->toBe('custom-lang');
    });

    it('returns default gitignore setting', function () {
        $config = new ChainedTranslatorConfig();

        expect($config->shouldAddGitignoreToCustomLangDirectory())->toBeTrue();
    });

    it('returns gitignore setting from config', function () {
        config()->set('laravel-chained-translator.add_gitignore_to_custom_lang_directory', false);
        $config = new ChainedTranslatorConfig();

        expect($config->shouldAddGitignoreToCustomLangDirectory())->toBeFalse();
    });

    it('throws when gitignore config value is not a boolean', function () {
        config()->set('laravel-chained-translator.add_gitignore_to_custom_lang_directory', 'false');
        $config = new ChainedTranslatorConfig();

        // Config::boolean() enforces strict type — non-boolean values throw an exception
        expect(fn () => $config->shouldAddGitignoreToCustomLangDirectory())
            ->toThrow(\InvalidArgumentException::class);
    });

    it('returns default group keys setting', function () {
        $config = new ChainedTranslatorConfig();

        expect($config->shouldGroupKeysInArray())->toBeTrue();
    });

    it('returns group keys setting from config when false', function () {
        config()->set('laravel-chained-translator.group_keys_in_array', false);
        $config = new ChainedTranslatorConfig();

        expect($config->shouldGroupKeysInArray())->toBeFalse();
    });

    it('returns group keys setting from config when true', function () {
        config()->set('laravel-chained-translator.group_keys_in_array', true);
        $config = new ChainedTranslatorConfig();

        expect($config->shouldGroupKeysInArray())->toBeTrue();
    });

    it('returns default JSON group name', function () {
        $config = new ChainedTranslatorConfig();

        expect($config->getJsonGroupName())->toBe('single');
    });

    it('returns JSON group name from config', function () {
        config()->set('laravel-chained-translator.json_group', 'translations');
        $config = new ChainedTranslatorConfig();

        expect($config->getJsonGroupName())->toBe('translations');
    });

    it('handles all custom config values', function () {
        config()->set('laravel-chained-translator', [
            'custom_lang_directory_name' => 'my-translations',
            'add_gitignore_to_custom_lang_directory' => false,
            'group_keys_in_array' => false,
            'json_group' => 'my-json',
        ]);

        $config = new ChainedTranslatorConfig();

        expect($config->getCustomLangDirectoryName())->toBe('my-translations');
        expect($config->shouldAddGitignoreToCustomLangDirectory())->toBeFalse();
        expect($config->shouldGroupKeysInArray())->toBeFalse();
        expect($config->getJsonGroupName())->toBe('my-json');
    });

    it('uses default values when config keys are missing', function () {
        config()->set('laravel-chained-translator', [
            'custom_lang_directory_name' => 'only-this',
        ]);

        $config = new ChainedTranslatorConfig();

        expect($config->getCustomLangDirectoryName())->toBe('only-this');
        expect($config->shouldAddGitignoreToCustomLangDirectory())->toBeTrue();
        expect($config->shouldGroupKeysInArray())->toBeTrue();
        expect($config->getJsonGroupName())->toBe('single');
    });
});
