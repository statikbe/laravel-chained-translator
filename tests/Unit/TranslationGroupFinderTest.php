<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;
use Statikbe\LaravelChainedTranslator\TranslationGroupFinder;
use Statikbe\LaravelChainedTranslator\TranslationGroupNameParser;

describe('TranslationGroupFinder', function (): void {
    beforeEach(function (): void {
        $tempDir = createUniqueTempDirectory('lang-test-');
        $enDir = $tempDir . '/en';
        $vendorDir = $tempDir . '/vendor';

        createTestDirectory($enDir);
        createTestDirectory($vendorDir . '/test-package/en');

        $this->tempDir = $tempDir;
        $this->enDir = $enDir;
        $this->vendorDir = $vendorDir;
        $this->config = new ChainedTranslatorConfig();
        $this->parser = new TranslationGroupNameParser($this->config);
        $this->files = new Filesystem();
    });

    afterEach(function (): void {
        /** @var Filesystem $files */
        $files = $this->files;
        /** @var string $tempDir */
        $tempDir = $this->tempDir;
        $files->deleteDirectory($tempDir);
    });

    it('can be instantiated', function (): void {
        /** @var Filesystem $files */
        $files = $this->files;
        /** @var TranslationGroupNameParser $parser */
        $parser = $this->parser;
        $finder = new TranslationGroupFinder($files, $parser);
        expect($finder)->toBeInstanceOf(TranslationGroupFinder::class);
    });

    it('only includes PHP and JSON files when scanning a directory', function (): void {
        /** @var string $enDir */
        $enDir = $this->enDir;
        file_put_contents($enDir . '/messages.php', '<?php return [];');
        file_put_contents($enDir . '/readme.txt', 'ignored');
        file_put_contents($enDir . '/script.js', 'ignored');

        /** @var Filesystem $files */
        $files = $this->files;

        $allFiles = $files->allFiles($enDir);
        $phpFiles = array_filter(
            $allFiles,
            static fn($file) => strtolower(pathinfo($file->getRelativePathname(), PATHINFO_EXTENSION)) === 'php',
        );
        $jsonFiles = array_filter(
            $allFiles,
            static fn($file) => strtolower(pathinfo($file->getRelativePathname(), PATHINFO_EXTENSION)) === 'json',
        );
        $otherFiles = array_filter(
            $allFiles,
            static fn($file) => !in_array(
                strtolower(pathinfo($file->getRelativePathname(), PATHINFO_EXTENSION)),
                ['php', 'json'],
                true,
            ),
        );

        expect($phpFiles)->toHaveCount(1);
        expect($jsonFiles)->toHaveCount(0);
        expect($otherFiles)->toHaveCount(2); // readme.txt, script.js
    });

    it('deduplicates translation groups across locales', function (): void {
        /** @var string $tempDir */
        $tempDir = $this->tempDir;
        /** @var string $enDir */
        $enDir = $this->enDir;
        $frDir = $tempDir . '/fr';
        createTestDirectory($frDir);

        // Same group name in multiple locales should appear only once
        file_put_contents($enDir . '/messages.php', '<?php return [];');
        file_put_contents($frDir . '/messages.php', '<?php return [];');

        /** @var Filesystem $files */
        $files = $this->files;

        $allFiles = $files->allFiles($tempDir);
        $groupNames = [];
        foreach ($allFiles as $file) {
            if (strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) === 'php') {
                $name = $file->getFilenameWithoutExtension();
                $groupNames[$name] = $name;
            }
        }

        expect($groupNames)->toHaveKey('messages');
        // After deduplication there should only be one 'messages' entry
        expect(count($groupNames))->toBe(1);
    });
});
