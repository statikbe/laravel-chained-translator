<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;
use Statikbe\LaravelChainedTranslator\ChainLoader;

/**
 * @var string $testPath
 * @var Filesystem $files
 * @var ChainLoader $loader
 * @var ChainedTranslatorConfig $config
 */

beforeEach(function (): void {
    /** @var string $testPath */
    $testPath = sys_get_temp_dir() . '/chained-translator-test-' . uniqid();
    mkdir($testPath, 0o755, true);
    mkdir($testPath . '/en', 0o755, true);

    $this->testPath = $testPath;
    $this->files = new Filesystem();
    $this->loader = Mockery::mock(ChainLoader::class);
    $this->config = new ChainedTranslatorConfig();
});

afterEach(function (): void {
    Mockery::close();

    /** @var string $testPath */
    $testPath = $this->testPath;
    if (is_dir($testPath)) {
        /** @var Filesystem $files */
        $files = $this->files;
        $files->deleteDirectory($testPath);
    }
});

it('can be instantiated', function (): void {
    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    expect($manager)->toBeInstanceOf(ChainedTranslationManager::class);
});

it('can save a translation', function (): void {
    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    $manager->save('en', 'messages', 'hello', 'Hello World');

    /** @var string $filePath */
    $filePath = $this->testPath . '/en/messages.php';
    expect($filePath)->toBeFile();

    /** @var array<string, mixed> $content */
    $content = include $filePath;
    expect($content)->toHaveKey('hello');
    expect($content['hello'])->toBe('Hello World');
});

it('can get custom translations', function (): void {
    /** @var string $filePath */
    $filePath = $this->testPath . '/en/messages.php';
    file_put_contents($filePath, "<?php\n\nreturn ['hello' => 'Hello World'];");

    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    $translations = $manager->getCustomTranslations('en', 'messages');

    expect($translations)->toBeInstanceOf(Collection::class);
    expect($translations->get('hello'))->toBe('Hello World');
});

it('returns empty collection when translation file does not exist', function (): void {
    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    $translations = $manager->getCustomTranslations('en', 'nonexistent');

    expect($translations)->toBeInstanceOf(Collection::class);
    expect($translations)->toBeEmpty();
});

it('can get translations for group with PHP loader', function (): void {
    $this->loader
        ->shouldReceive('load')
        ->with('en', 'messages', null)
        ->once()
        ->andReturn(['hello' => 'Hello World']);

    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    $translations = $manager->getTranslationsForGroup('en', 'messages');

    expect($translations)->toBeArray();
    expect($translations)->toHaveKey('hello');
    expect($translations['hello'])->toBe('Hello World');
});

it('can get translations for group with JSON loader', function (): void {
    $this->loader
        ->shouldReceive('load')
        ->with('en', '*', '*')
        ->once()
        ->andReturn(['hello' => 'Hello World']);

    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    $translations = $manager->getTranslationsForGroup('en', 'single');

    expect($translations)->toBeArray();
    expect($translations)->toHaveKey('hello');
    expect($translations['hello'])->toBe('Hello World');
});

it('compresses hierarchical translations to dot notation', function (): void {
    $this->loader
        ->shouldReceive('load')
        ->andReturn([
            'messages' => [
                'greeting' => [
                    'morning' => 'Good morning',
                    'evening' => 'Good evening',
                ],
            ],
        ]);

    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    $translations = $manager->getTranslationsForGroup('en', 'messages');

    expect($translations)->toHaveKey('messages.greeting.morning');
    expect($translations)->toHaveKey('messages.greeting.evening');
    expect($translations['messages.greeting.morning'])->toBe('Good morning');
});

it('handles file write errors gracefully', function (): void {
    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    $manager->save('en', 'test', 'key', 'value');

    /** @var string $filePath */
    $filePath = $this->testPath . '/en/test.php';
    expect($filePath)->toBeFile();

    /** @var array<string, mixed> $content */
    $content = include $filePath;
    expect($content['key'])->toBe('value');
});
