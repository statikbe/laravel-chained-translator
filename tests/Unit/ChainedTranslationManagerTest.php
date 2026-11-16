<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Translation\FileLoader;
use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;
use Statikbe\LaravelChainedTranslator\ChainLoader;
use Statikbe\LaravelChainedTranslator\Exceptions\InvalidTranslationPathException;
use Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException;
use Statikbe\LaravelChainedTranslator\NonPackageFileLoader;

/**
 * $testPath is the custom lang directory, $langPath the app's lang directory. The loader chains both,
 * as the service provider does.
 *
 * @var string $testPath
 * @var string $langPath
 * @var Filesystem $files
 * @var ChainLoader $loader
 * @var ChainedTranslatorConfig $config
 */

beforeEach(function (): void {
    /** @var string $testPath */
    $testPath = sys_get_temp_dir() . '/chained-translator-test-' . uniqid();
    mkdir($testPath, 0o755, true);
    mkdir($testPath . '/en', 0o755, true);
    $langPath = createUniqueTempDirectory('chained-translator-lang-');

    $this->testPath = $testPath;
    $this->langPath = $langPath;
    $this->files = new Filesystem();
    $this->loader = new ChainLoader();
    $this->loader->addLoader(new NonPackageFileLoader($this->files, $testPath));
    $this->loader->addLoader(new FileLoader($this->files, $langPath));
    $this->config = new ChainedTranslatorConfig();
});

afterEach(function (): void {
    /** @var Filesystem $files */
    $files = $this->files;
    $files->deleteDirectory($this->testPath);
    $files->deleteDirectory($this->langPath);
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

it('gets the translations of a PHP group with the custom lines over the app lines', function (): void {
    mkdir($this->langPath . '/en');
    file_put_contents(
        $this->langPath . '/en/messages.php',
        "<?php return ['hello' => 'App hello', 'bye' => 'App bye'];",
    );
    file_put_contents($this->testPath . '/en/messages.php', "<?php return ['hello' => 'Custom hello'];");
    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    expect($manager->getTranslationsForGroup('en', 'messages'))->toEqualCanonicalizing([
        'hello' => 'Custom hello',
        'bye' => 'App bye',
    ]);
});

it('gets the JSON translations with the custom lines over the app lines', function (): void {
    file_put_contents($this->langPath . '/en.json', '{"Hello": "App hello", "Bye": "App bye"}');
    file_put_contents($this->testPath . '/en.json', '{"Hello": "Custom hello"}');
    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    expect($manager->getTranslationsForGroup('en', 'json-file'))->toEqualCanonicalizing([
        'Hello' => 'Custom hello',
        'Bye' => 'App bye',
    ]);
});

it('returns nested translations in dot notation', function (): void {
    mkdir($this->langPath . '/en');
    file_put_contents(
        $this->langPath . '/en/messages.php',
        "<?php return ['greeting' => ['morning' => 'Good morning', 'evening' => 'Good evening']];",
    );
    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    expect($manager->getTranslationsForGroup('en', 'messages'))->toBe([
        'greeting.morning' => 'Good morning',
        'greeting.evening' => 'Good evening',
    ]);
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

it('refuses to save a translation with a group outside the language directory', function (): void {
    /** @var string $testPath */
    $testPath = $this->testPath;
    $languagePath = $testPath . '/lang-custom';
    mkdir($languagePath . '/en', 0o755, true);
    $manager = new ChainedTranslationManager($this->files, $this->loader, $languagePath, $this->config);

    expect(fn() => $manager->save('en', '../../outside', 'key', 'value'))
        ->toThrow(InvalidTranslationPathException::class);
    expect($testPath . '/outside.php')->not->toBeFile();
});

it('refuses to load translations with an invalid locale or group', function (string $locale, string $group): void {
    // A mock, to prove nothing is loaded before the locale and group are validated.
    $loader = Mockery::mock(ChainLoader::class);
    $loader->shouldNotReceive('load');
    $manager = new ChainedTranslationManager($this->files, $loader, $this->testPath, $this->config);

    expect(fn() => $manager->getTranslationsForGroup($locale, $group))->toThrow(InvalidTranslationPathException::class);
})->with([
    'group traversal' => ['en', '../../config/app'],
    'locale traversal' => ['../../config', 'app'],
    'namespace traversal' => ['en', '../..::app'],
]);

it('keeps a custom JSON file it cannot parse instead of overwriting it on save', function (): void {
    $contents = '{"hello": "Hello", "world": "World",}';
    file_put_contents($this->testPath . '/en.json', $contents);
    $manager = new ChainedTranslationManager($this->files, $this->loader, $this->testPath, $this->config);

    expect(fn() => $manager->save('en', 'json-file', 'new', 'New'))->toThrow(SaveTranslationFileException::class);
    expect(file_get_contents($this->testPath . '/en.json'))->toBe($contents);
});
