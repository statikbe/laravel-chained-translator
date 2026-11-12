<?php

declare(strict_types=1);

use Statikbe\LaravelChainedTranslator\Exceptions\InvalidTranslationPathException;
use Statikbe\LaravelChainedTranslator\TranslationGroupNameParser;

describe('TranslationGroupNameParser', function () {
    it('returns the configured JSON group name', function () {
        $config = getTestConfig(['json_group' => 'my-json-group']);
        $parser = new TranslationGroupNameParser($config);

        expect($parser->getJsonGroupName())->toBe('my-json-group');
    });

    it('identifies JSON group correctly', function () {
        $config = getTestConfig(['json_group' => 'json-file']);
        $parser = new TranslationGroupNameParser($config);

        expect($parser->isJsonGroup('json-file'))->toBeTrue();
        expect($parser->isJsonGroup('messages'))->toBeFalse();
        expect($parser->isJsonGroup('other'))->toBeFalse();
    });

    it('extracts namespace from group string', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);

        [$namespace, $group] = $parser->extractNamespace('vendor::messages');

        expect($namespace)->toBe('vendor');
        expect($group)->toBe('messages');
    });

    it('returns null namespace when no namespace in group', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);

        [$namespace, $group] = $parser->extractNamespace('messages');

        expect($namespace)->toBeNull();
        expect($group)->toBe('messages');
    });

    it('handles namespaced groups with subfolders', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);

        [$namespace, $group] = $parser->extractNamespace('vendor::sub/folder/messages');

        expect($namespace)->toBe('vendor');
        expect($group)->toBe('sub/folder/messages');
    });

    it('extracts subfolders from group string', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);

        [$subfolders, $group] = $parser->extractSubfolders('sub/folder/messages');

        expect($subfolders)->toBe('sub/folder');
        expect($group)->toBe('messages');
    });

    it('returns null subfolders when no subfolders in group', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);

        [$subfolders, $group] = $parser->extractSubfolders('messages');

        expect($subfolders)->toBeNull();
        expect($group)->toBe('messages');
    });

    it('handles deeply nested subfolders', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);

        [$subfolders, $group] = $parser->extractSubfolders('a/b/c/d/messages');

        expect($subfolders)->toBe('a/b/c/d');
        expect($group)->toBe('messages');
    });

    it('handles single level subfolder', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);

        [$subfolders, $group] = $parser->extractSubfolders('admin/messages');

        expect($subfolders)->toBe('admin');
        expect($group)->toBe('messages');
    });

    it('can chain extractNamespace and extractSubfolders', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);

        [$namespace, $afterNamespace] = $parser->extractNamespace('vendor::sub/folder/messages');
        [$subfolders, $groupName] = $parser->extractSubfolders($afterNamespace);

        expect($namespace)->toBe('vendor');
        expect($subfolders)->toBe('sub/folder');
        expect($groupName)->toBe('messages');
    });

    it('accepts valid locales', function (string $locale) {
        $parser = new TranslationGroupNameParser(getTestConfig());

        $parser->assertValidLocale($locale);

        expect(true)->toBeTrue();
    })->with(['en', 'en_US', 'pt-BR', 'zh_Hant']);

    it('rejects locales that are not a single safe path segment', function (string $locale) {
        $parser = new TranslationGroupNameParser(getTestConfig());

        expect(fn() => $parser->assertValidLocale($locale))->toThrow(InvalidTranslationPathException::class);
    })->with(['', '.', '..', '../en', 'en/..', 'en/../..', 'en\\..', '/etc', "en\0", "en\n", 'en.json']);

    it('accepts valid groups', function (string $group) {
        $parser = new TranslationGroupNameParser(getTestConfig());

        $parser->assertValidGroup($group);

        expect(true)->toBeTrue();
    })->with([
        'messages',
        'admin/messages',
        'admin/sub-folder/my_messages',
        'filament-panels::layout',
        'vendor::sub/folder/messages',
        'messages.v2',
        'json-file',
    ]);

    it('rejects groups that could resolve outside the language directory', function (string $group) {
        $parser = new TranslationGroupNameParser(getTestConfig());

        expect(fn() => $parser->assertValidGroup($group))->toThrow(InvalidTranslationPathException::class);
    })->with([
        '',
        '..',
        '../messages',
        '../../config/app',
        'admin/../../messages',
        'admin/..',
        './messages',
        '/messages',
        'admin//messages',
        'admin/',
        '.hidden',
        '..::messages',
        'foo/..::messages',
        '::messages',
        'vendor::../messages',
        'vendor::a::b',
        'admin\\..\\messages',
        "messages\0",
    ]);
});
