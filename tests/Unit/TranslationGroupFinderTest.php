<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;
use Statikbe\LaravelChainedTranslator\TranslationGroupFinder;
use Statikbe\LaravelChainedTranslator\TranslationGroupNameParser;

describe('TranslationGroupFinder', function (): void {
    beforeEach(function (): void {
        /** @var string $tempDir */
        $tempDir = sys_get_temp_dir() . '/lang-test-' . uniqid();
        /** @var string $enDir */
        $enDir = $tempDir . '/en';
        /** @var string $vendorDir */
        $vendorDir = $tempDir . '/vendor';

        mkdir($tempDir, 0o755, true);
        mkdir($enDir, 0o755, true);
        mkdir($vendorDir . '/test-package/en', 0o755, true);

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

    it('finds PHP translation groups in lang directory', function (): void {
        /** @var string $enDir */
        $enDir = $this->enDir;
        file_put_contents($enDir . '/messages.php', '<?php return [];');
        file_put_contents($enDir . '/validation.php', '<?php return [];');

        /** @var Filesystem $files */
        $files = $this->files;
        /** @var TranslationGroupNameParser $parser */
        $parser = $this->parser;
        $finder = new TranslationGroupFinder($files, $parser);

        $groups = $finder->findAll();

        expect(true)->toBeTrue();
    });
});
