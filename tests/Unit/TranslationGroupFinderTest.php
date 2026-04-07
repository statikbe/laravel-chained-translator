<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Statikbe\LaravelChainedTranslator\TranslationGroupFinder;
use Statikbe\LaravelChainedTranslator\TranslationGroupNameParser;

describe('TranslationGroupFinder', function (): void {
    beforeEach(function (): void {
        $tempDir = createUniqueTempDirectory('lang-test-');

        createTestDirectory($tempDir . '/en');

        $this->tempDir = $tempDir;
        $this->files = new Filesystem();
        $this->parser = new TranslationGroupNameParser();

        // Override lang_path to use our temp directory
        app()->useLangPath($tempDir);
    });

    afterEach(function (): void {
        /** @var Filesystem $files */
        $files = $this->files;
        /** @var string $tempDir */
        $tempDir = $this->tempDir;
        $files->deleteDirectory($tempDir);
    });

    it('can be instantiated', function (): void {
        $finder = new TranslationGroupFinder($this->files, $this->parser);
        expect($finder)->toBeInstanceOf(TranslationGroupFinder::class);
    });

    it('only includes PHP and JSON files', function (): void {
        file_put_contents($this->tempDir . '/en/messages.php', '<?php return [];');
        file_put_contents($this->tempDir . '/en/readme.txt', 'ignored');
        file_put_contents($this->tempDir . '/en/script.js', 'ignored');

        $finder = new TranslationGroupFinder($this->files, $this->parser);
        $groups = $finder->findAll();

        expect($groups)->toContain('messages');
        expect($groups)->not->toContain('readme');
        expect($groups)->not->toContain('script');
    });

    it('deduplicates translation groups across locales', function (): void {
        createTestDirectory($this->tempDir . '/fr');

        file_put_contents($this->tempDir . '/en/messages.php', '<?php return [];');
        file_put_contents($this->tempDir . '/fr/messages.php', '<?php return [];');

        $finder = new TranslationGroupFinder($this->files, $this->parser);
        $groups = $finder->findAll();

        $messagesCount = count(array_filter($groups, fn ($g) => $g === 'messages'));
        expect($messagesCount)->toBe(1);
    });

    it('discovers groups in subdirectories', function (): void {
        createTestDirectory($this->tempDir . '/en/admin');

        file_put_contents($this->tempDir . '/en/admin/users.php', '<?php return [];');

        $finder = new TranslationGroupFinder($this->files, $this->parser);
        $groups = $finder->findAll();

        expect($groups)->toContain('admin/users');
    });

    it('discovers deeply nested groups', function (): void {
        createTestDirectory($this->tempDir . '/en/admin/settings');

        file_put_contents($this->tempDir . '/en/admin/settings/general.php', '<?php return [];');

        $finder = new TranslationGroupFinder($this->files, $this->parser);
        $groups = $finder->findAll();

        expect($groups)->toContain('admin/settings/general');
    });

    it('discovers vendor groups', function (): void {
        createTestDirectory($this->tempDir . '/vendor/test-package/en');

        file_put_contents($this->tempDir . '/vendor/test-package/en/messages.php', '<?php return [];');

        $finder = new TranslationGroupFinder($this->files, $this->parser);
        $groups = $finder->findAll();

        expect($groups)->toContain('test-package::messages');
    });

    it('discovers JSON translation files', function (): void {
        file_put_contents($this->tempDir . '/en.json', '{}');

        $finder = new TranslationGroupFinder($this->files, $this->parser);
        $groups = $finder->findAll();

        expect($groups)->toContain($this->parser->getJsonGroupName());
    });

    it('uses forward slashes in group identifiers for subdirectories', function (): void {
        createTestDirectory($this->tempDir . '/en/sub/folder');

        file_put_contents($this->tempDir . '/en/sub/folder/messages.php', '<?php return [];');

        $finder = new TranslationGroupFinder($this->files, $this->parser);
        $groups = $finder->findAll();

        expect($groups)->toContain('sub/folder/messages');
        // Group identifiers should never contain backslashes
        foreach ($groups as $group) {
            expect($group)->not->toContain('\\');
        }
    });
});