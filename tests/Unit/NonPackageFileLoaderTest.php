<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Statikbe\LaravelChainedTranslator\NonPackageFileLoader;

describe('NonPackageFileLoader', function () {
    it('extends Laravel FileLoader', function () {
        $files = new Filesystem();
        $loader = new NonPackageFileLoader($files, '/path/to/lang');

        expect($loader)->toBeInstanceOf(\Illuminate\Translation\FileLoader::class);
    });

    it('returns empty array for namespaced translations', function () {
        $testPath = sys_get_temp_dir() . '/test-lang-' . uniqid();
        createTestDirectory($testPath);

        $files = new Filesystem();
        $loader = new NonPackageFileLoader($files, $testPath);

        // Add a namespace hint
        $loader->addNamespace('test-vendor', $testPath);

        // Load namespaced translations - should return empty due to override
        $result = $loader->load('en', 'messages', 'test-vendor');

        expect($result)->toBeEmpty();

        cleanupTestDirectory($testPath);
    });

    it('loads normal translations when no namespace', function () {
        $testPath = sys_get_temp_dir() . '/test-lang-' . uniqid();
        $localePath = $testPath . '/en';
        createTestDirectory($localePath);

        // Create a PHP translation file
        file_put_contents($localePath . '/messages.php', '<?php return ["hello" => "Hello", "world" => "World"];');

        $files = new Filesystem();
        $loader = new NonPackageFileLoader($files, $testPath);

        $result = $loader->load('en', 'messages');

        expect($result)->toBe(['hello' => 'Hello', 'world' => 'World']);

        cleanupTestDirectory($testPath);
    });

    it('loads JSON translations', function () {
        $testPath = sys_get_temp_dir() . '/test-lang-' . uniqid();
        createTestDirectory($testPath);

        // Create a JSON translation file
        file_put_contents($testPath . '/en.json', json_encode(['hello' => 'Hello JSON']));

        $files = new Filesystem();
        $loader = new NonPackageFileLoader($files, $testPath);
        $loader->addJsonPath($testPath);

        $result = $loader->load('en', '*', '*');

        expect($result)->toBe(['hello' => 'Hello JSON']);

        cleanupTestDirectory($testPath);
    });

    it('returns empty array for non-existent locale', function () {
        $testPath = sys_get_temp_dir() . '/test-lang-' . uniqid();
        createTestDirectory($testPath);

        $files = new Filesystem();
        $loader = new NonPackageFileLoader($files, $testPath);

        $result = $loader->load('nonexistent', 'messages');

        expect($result)->toBeEmpty();

        cleanupTestDirectory($testPath);
    });

    it('returns empty array for non-existent group', function () {
        $testPath = sys_get_temp_dir() . '/test-lang-' . uniqid();
        $localePath = $testPath . '/en';
        createTestDirectory($localePath);

        $files = new Filesystem();
        $loader = new NonPackageFileLoader($files, $testPath);

        $result = $loader->load('en', 'nonexistent');

        expect($result)->toBeEmpty();

        cleanupTestDirectory($testPath);
    });
});
