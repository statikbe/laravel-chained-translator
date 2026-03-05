<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;
use Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException;
use Statikbe\LaravelChainedTranslator\TranslationFileWriter;
use Statikbe\LaravelChainedTranslator\TranslationGroupNameParser;

describe('TranslationFileWriter', function (): void {
    beforeEach(function (): void {
        /** @var string $testPath */
        $testPath = sys_get_temp_dir() . '/test-translations-' . uniqid();
        createTestDirectory($testPath);
        $this->testPath = $testPath;
    });

    afterEach(function (): void {
        /** @var string $testPath */
        $testPath = $this->testPath;
        cleanupTestDirectory($testPath);
    });

    it('can be instantiated', function (): void {
        $files = new Filesystem();
        $config = new ChainedTranslatorConfig();
        $parser = new TranslationGroupNameParser($config);

        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        expect($writer)->toBeInstanceOf(TranslationFileWriter::class);
    });

    it('reads empty collection for non-existent group', function (): void {
        $files = new Filesystem();
        $config = new ChainedTranslatorConfig();
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $result = $writer->readGroupTranslations('en', 'messages');

        expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($result)->toBeEmpty();
    });

    it('reads PHP translation file correctly', function (): void {
        /** @var string $testPath */
        $testPath = $this->testPath;
        $localePath = $testPath . '/en';
        createTestDirectory($localePath);
        file_put_contents($localePath . '/messages.php', '<?php return ["hello" => "Hello", "world" => "World"];');

        $files = new Filesystem();
        $config = new ChainedTranslatorConfig();
        $parser = new TranslationGroupNameParser($config);
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $result = $writer->readGroupTranslations('en', 'messages');

        expect($result->toArray())->toBe(['hello' => 'Hello', 'world' => 'World']);
    });

    it('reads JSON translation file correctly', function (): void {
        /** @var string $testPath */
        $testPath = $this->testPath;
        file_put_contents($testPath . '/en.json', json_encode([
            'hello' => 'Hello JSON',
            'world' => 'World JSON',
        ]));

        $files = new Filesystem();
        $config = getTestConfig(['json_group' => 'json-file']);
        $parser = new TranslationGroupNameParser($config);
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $result = $writer->readGroupTranslations('en', 'json-file');

        expect($result->toArray())->toBe(['hello' => 'Hello JSON', 'world' => 'World JSON']);
    });

    it('writes PHP translation file', function (): void {
        $files = new Filesystem();
        $config = getTestConfig(['group_keys_in_array' => false]);
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $translations = collect(['hello' => 'Hello', 'world' => 'World']);

        $writer->writeGroupTranslations('en', 'messages', $translations);

        $filePath = $testPath . '/en/messages.php';
        expect($filePath)->toBeFile();

        $content = file_get_contents($filePath);
        expect($content)->toContain('<?php');
        expect($content)->toContain('return');
        expect($content)->toContain('hello');
        expect($content)->toContain('Hello');
    });

    it('writes JSON translation file', function (): void {
        $files = new Filesystem();
        $config = getTestConfig(['json_group' => 'json-file']);
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $translations = collect(['hello' => 'Hello JSON']);

        $writer->writeGroupTranslations('en', 'json-file', $translations);

        $filePath = $testPath . '/en.json';
        expect($filePath)->toBeFile();

        $content = file_get_contents($filePath);
        /** @var array<string, string>|null $decoded */
        $decoded = json_decode($content, true);
        expect($decoded)->toBe(['hello' => 'Hello JSON']);
    });

    it('writes to custom language path when specified', function (): void {
        $customPath = sys_get_temp_dir() . '/custom-lang-' . uniqid();
        createTestDirectory($customPath);

        $files = new Filesystem();
        $config = getTestConfig(['group_keys_in_array' => false]);
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $translations = collect(['key' => 'value']);

        $writer->writeGroupTranslations('en', 'messages', $translations, $customPath);

        $filePath = $customPath . '/en/messages.php';
        expect($filePath)->toBeFile();

        cleanupTestDirectory($customPath);
    });

    it('creates directory structure when writing', function (): void {
        $files = new Filesystem();
        $config = getTestConfig(['group_keys_in_array' => false]);
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $translations = collect(['key' => 'value']);

        $writer->writeGroupTranslations('en', 'messages', $translations);

        expect($testPath . '/en')->toBeDirectory();
    });

    it('checks if locale folder exists', function (): void {
        $files = new Filesystem();
        $config = new ChainedTranslatorConfig();
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        expect($writer->localeFolderExists('en'))->toBeFalse();

        createTestDirectory($testPath . '/en');

        expect($writer->localeFolderExists('en'))->toBeTrue();
    });

    it('creates locale folder', function (): void {
        $files = new Filesystem();
        $config = new ChainedTranslatorConfig();
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $result = $writer->createLocaleFolder('en');

        expect($result)->toBeTrue();
        expect($testPath . '/en')->toBeDirectory();
    });

    it('gets correct path for PHP group', function (): void {
        $files = new Filesystem();
        $config = new ChainedTranslatorConfig();
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $path = $writer->getGroupPath('en', 'messages');

        expect($path)->toBe($testPath . '/en/messages.php');
    });

    it('gets correct path for JSON group', function (): void {
        $files = new Filesystem();
        $config = getTestConfig(['json_group' => 'json-file']);
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $path = $writer->getGroupPath('en', 'json-file');

        expect($path)->toBe($testPath . '/en.json');
    });

    it('handles namespaced group paths', function (): void {
        $files = new Filesystem();
        $config = new ChainedTranslatorConfig();
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $path = $writer->getGroupPath('en', 'vendor::messages');

        expect($path)->toBe($testPath . '/vendor/vendor/en/messages.php');
    });

    it('handles subfolder group paths', function (): void {
        $files = new Filesystem();
        $config = new ChainedTranslatorConfig();
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $path = $writer->getGroupPath('en', 'admin/messages');

        expect($path)->toBe($testPath . '/en/admin/messages.php');
    });

    it('groups keys in array when config is enabled', function (): void {
        $files = new Filesystem();
        $config = getTestConfig(['group_keys_in_array' => true]);
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $translations = collect(['level1.level2.key' => 'value']);

        $writer->writeGroupTranslations('en', 'messages', $translations);

        $filePath = $testPath . '/en/messages.php';
        $content = file_get_contents($filePath);

        expect($content)->toContain("'level1'");
        expect($content)->toContain("'level2'");
    });

    it('keeps dotted keys when config is disabled', function (): void {
        $files = new Filesystem();
        $config = getTestConfig(['group_keys_in_array' => false]);
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        $translations = collect(['level1.level2.key' => 'value']);

        $writer->writeGroupTranslations('en', 'messages', $translations);

        $filePath = $testPath . '/en/messages.php';
        $content = file_get_contents($filePath);

        expect($content)->toContain('level1.level2.key');
    });

    it('throws exception when write fails', function (): void {
        /** @var Filesystem&\Mockery\MockInterface $files */
        $files = Mockery::mock(Filesystem::class);
        $files->shouldReceive('exists')->andReturn(false);
        $files->shouldReceive('makeDirectory')->andReturn(true);
        $files->shouldReceive('put')->andReturn(false);

        $config = getTestConfig(['group_keys_in_array' => false]);
        $parser = new TranslationGroupNameParser($config);
        /** @var string $testPath */
        $testPath = $this->testPath;
        $writer = new TranslationFileWriter($files, $parser, $testPath, $config);

        expect(fn() => $writer->writeGroupTranslations('en', 'messages', collect([
            'key' => 'value',
        ])))->toThrow(SaveTranslationFileException::class);
    });
});
