<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use Statikbe\LaravelChainedTranslator\BaseTranslationServiceProvider;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;
use Statikbe\LaravelChainedTranslator\TranslationServiceProvider;

uses(TestCase::class)->in(__DIR__);

/**
 * Setup the test environment for package tests
 */
function getPackageProviders($app): array
{
    return [
        TranslationServiceProvider::class,
        BaseTranslationServiceProvider::class,
    ];
}

/**
 * Get the custom config for testing
 */
function getTestConfig(array $overrides = []): ChainedTranslatorConfig
{
    return new class($overrides) extends ChainedTranslatorConfig {
        /** @var array<string, mixed> */
        private array $overrides;

        public function __construct(array $overrides)
        {
            $this->overrides = $overrides;
        }

        public function getCustomLangDirectoryName(): string
        {
            /** @var string */
            return $this->overrides['custom_lang_directory_name'] ?? 'lang-custom';
        }

        public function shouldAddGitignoreToCustomLangDirectory(): bool
        {
            /** @var bool */
            return $this->overrides['add_gitignore_to_custom_lang_directory'] ?? true;
        }

        public function shouldGroupKeysInArray(): bool
        {
            /** @var bool */
            return $this->overrides['group_keys_in_array'] ?? false;
        }

        public function getJsonGroupName(): string
        {
            /** @var string */
            return $this->overrides['json_group'] ?? 'json-file';
        }
    };
}

/**
 * Create a temporary test directory
 */
function createTestDirectory(string $path): void
{
    $filesystem = new Filesystem();
    if (!$filesystem->exists($path)) {
        $filesystem->makeDirectory($path, 0o755, true);
    }
}

/**
 * Create a unique temporary directory under sys_get_temp_dir() with an optional prefix.
 * The directory is created immediately and its path is returned.
 */
function createUniqueTempDirectory(string $prefix = 'test-'): string
{
    $path = sys_get_temp_dir() . '/' . $prefix . uniqid();
    createTestDirectory($path);

    return $path;
}

/**
 * Clean up test directory
 */
function cleanupTestDirectory(string $path): void
{
    $filesystem = new Filesystem();
    if ($filesystem->exists($path)) {
        $filesystem->deleteDirectory($path);
    }
}

/**
 * Create a mock loader that implements the Loader interface
 */
function createMockLoader(array $translations = []): \Illuminate\Contracts\Translation\Loader
{
    return new class($translations) implements \Illuminate\Contracts\Translation\Loader {
        /** @var array<string, array<string, array<string, mixed>>> */
        private array $translations;
        /** @var array<string, string> */
        private array $namespaces = [];

        public function __construct(array $translations)
        {
            $this->translations = $translations;
        }

        public function load($locale, $group, $namespace = null): array
        {
            $key = $namespace ? "{$namespace}::{$group}" : $group;
            /** @var array<string, mixed> */
            return $this->translations[$locale][$key] ?? [];
        }

        public function addNamespace($namespace, $hint): void
        {
            $this->namespaces[$namespace] = $hint;
        }

        public function addJsonPath($path): void
        {
            // No-op for mock
        }

        public function namespaces(): array
        {
            return $this->namespaces;
        }
    };
}
