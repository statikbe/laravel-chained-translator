<?php

declare(strict_types=1);

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

    it('pulls namespace from group string', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);
        $group = 'vendor::messages';

        $namespace = $parser->pullNamespace($group);

        expect($namespace)->toBe('vendor');
        expect($group)->toBe('messages');
    });

    it('returns null when no namespace in group', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);
        $group = 'messages';

        $namespace = $parser->pullNamespace($group);

        expect($namespace)->toBeNull();
        expect($group)->toBe('messages');
    });

    it('handles namespaced groups with subfolders', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);
        $group = 'vendor::sub/folder/messages';

        $namespace = $parser->pullNamespace($group);

        expect($namespace)->toBe('vendor');
        expect($group)->toBe('sub/folder/messages');
    });

    it('pulls subfolders from group string', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);
        $group = 'sub/folder/messages';

        $subfolders = $parser->pullSubfolders($group);

        expect($subfolders)->toBe('sub/folder');
        expect($group)->toBe('messages');
    });

    it('returns null when no subfolders in group', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);
        $group = 'messages';

        $subfolders = $parser->pullSubfolders($group);

        expect($subfolders)->toBeNull();
        expect($group)->toBe('messages');
    });

    it('handles deeply nested subfolders', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);
        $group = 'a/b/c/d/messages';

        $subfolders = $parser->pullSubfolders($group);

        expect($subfolders)->toBe('a/b/c/d');
        expect($group)->toBe('messages');
    });

    it('handles single level subfolder', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);
        $group = 'admin/messages';

        $subfolders = $parser->pullSubfolders($group);

        expect($subfolders)->toBe('admin');
        expect($group)->toBe('messages');
    });

    it('can chain pullNamespace and pullSubfolders', function () {
        $config = getTestConfig();
        $parser = new TranslationGroupNameParser($config);
        $group = 'vendor::sub/folder/messages';

        $namespace = $parser->pullNamespace($group);
        $subfolders = $parser->pullSubfolders($group);

        expect($namespace)->toBe('vendor');
        expect($subfolders)->toBe('sub/folder');
        expect($group)->toBe('messages');
    });
});
