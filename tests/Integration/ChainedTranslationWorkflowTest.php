<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;
use Statikbe\LaravelChainedTranslator\ChainLoader;
use Statikbe\LaravelChainedTranslator\NonPackageFileLoader;

describe('Integration - Chained Translation Workflow', function (): void {
    beforeEach(function (): void {
        /** @var string $testBasePath */
        $testBasePath = sys_get_temp_dir() . '/integration-test-' . uniqid();
        /** @var string $defaultLangPath */
        $defaultLangPath = $testBasePath . '/lang';
        /** @var string $customLangPath */
        $customLangPath = $testBasePath . '/lang-custom';

        mkdir($testBasePath, 0o755, true);
        mkdir($defaultLangPath . '/en', 0o755, true);
        mkdir($customLangPath . '/en', 0o755, true);

        $this->testBasePath = $testBasePath;
        $this->defaultLangPath = $defaultLangPath;
        $this->customLangPath = $customLangPath;
        $this->files = new Filesystem();
    });

    afterEach(function (): void {
        /** @var string $testBasePath */
        $testBasePath = $this->testBasePath;
        if (is_dir($testBasePath)) {
            /** @var Filesystem $files */
            $files = $this->files;
            $files->deleteDirectory($testBasePath);
        }
    });

    it('loads translations from default path when no custom override', function (): void {
        /** @var string $defaultLangPath */
        $defaultLangPath = $this->defaultLangPath;
        file_put_contents(
            $defaultLangPath . '/en/messages.php',
            '<?php return ["hello" => "Hello", "goodbye" => "Goodbye"];',
        );

        /** @var Filesystem $files */
        $files = $this->files;
        $defaultLoader = new \Illuminate\Translation\FileLoader($files, $defaultLangPath);
        /** @var string $customLangPath */
        $customLangPath = $this->customLangPath;
        $customLoader = new NonPackageFileLoader($files, $customLangPath);

        $chainLoader = new ChainLoader();
        $chainLoader->addLoader($customLoader);
        $chainLoader->addLoader($defaultLoader);

        $translations = $chainLoader->load('en', 'messages');

        expect($translations)->toBe(['hello' => 'Hello', 'goodbye' => 'Goodbye']);
    });

    it('custom translations override default translations', function (): void {
        /** @var string $defaultLangPath */
        $defaultLangPath = $this->defaultLangPath;
        /** @var string $customLangPath */
        $customLangPath = $this->customLangPath;

        file_put_contents(
            $defaultLangPath . '/en/messages.php',
            '<?php return ["hello" => "Hello", "goodbye" => "Goodbye"];',
        );

        file_put_contents($customLangPath . '/en/messages.php', '<?php return ["hello" => "Hi"];');

        /** @var Filesystem $files */
        $files = $this->files;
        $defaultLoader = new \Illuminate\Translation\FileLoader($files, $defaultLangPath);
        $customLoader = new NonPackageFileLoader($files, $customLangPath);

        $chainLoader = new ChainLoader();
        $chainLoader->addLoader($customLoader);
        $chainLoader->addLoader($defaultLoader);

        /** @var array<string, string> $translations */
        $translations = $chainLoader->load('en', 'messages');

        expect($translations['hello'])->toBe('Hi');
        expect($translations['goodbye'])->toBe('Goodbye');
    });

    it('saves translation to custom path', function (): void {
        $config = new ChainedTranslatorConfig();

        /** @var string $defaultLangPath */
        $defaultLangPath = $this->defaultLangPath;
        /** @var string $customLangPath */
        $customLangPath = $this->customLangPath;
        /** @var Filesystem $files */
        $files = $this->files;

        $defaultLoader = new \Illuminate\Translation\FileLoader($files, $defaultLangPath);
        $customLoader = new NonPackageFileLoader($files, $customLangPath);

        $chainLoader = new ChainLoader();
        $chainLoader->addLoader($customLoader);
        $chainLoader->addLoader($defaultLoader);

        $manager = new ChainedTranslationManager($files, $chainLoader, $customLangPath, $config);

        $manager->save('en', 'messages', 'hello', 'Hello World');

        expect($customLangPath . '/en/messages.php')->toBeFile();

        /** @var array<string, string> $content */
        $content = include $customLangPath . '/en/messages.php';
        expect($content['hello'])->toBe('Hello World');
    });

    it('handles JSON translations in chain', function (): void {
        /** @var string $defaultLangPath */
        $defaultLangPath = $this->defaultLangPath;
        /** @var string $customLangPath */
        $customLangPath = $this->customLangPath;

        file_put_contents($defaultLangPath . '/en.json', json_encode(['hello' => 'Hello', 'world' => 'World']));
        file_put_contents($customLangPath . '/en.json', json_encode(['hello' => 'Hi']));

        /** @var Filesystem $files */
        $files = $this->files;
        $defaultLoader = new \Illuminate\Translation\FileLoader($files, $defaultLangPath);
        $defaultLoader->addJsonPath($defaultLangPath);

        $customLoader = new NonPackageFileLoader($files, $customLangPath);
        $customLoader->addJsonPath($customLangPath);

        $chainLoader = new ChainLoader();
        $chainLoader->addLoader($customLoader);
        $chainLoader->addLoader($defaultLoader);

        /** @var array<string, string> $translations */
        $translations = $chainLoader->load('en', '*', '*');

        expect($translations['hello'])->toBe('Hi');
        expect($translations['world'])->toBe('World');
    });

    it('handles nested translation groups', function (): void {
        /** @var string $defaultLangPath */
        $defaultLangPath = $this->defaultLangPath;
        mkdir($defaultLangPath . '/en/admin', 0o755, true);
        file_put_contents($defaultLangPath . '/en/admin/users.php', '<?php return ["title" => "Users"];');

        /** @var Filesystem $files */
        $files = $this->files;
        /** @var string $customLangPath */
        $customLangPath = $this->customLangPath;
        $defaultLoader = new \Illuminate\Translation\FileLoader($files, $defaultLangPath);
        $customLoader = new NonPackageFileLoader($files, $customLangPath);

        $chainLoader = new ChainLoader();
        $chainLoader->addLoader($customLoader);
        $chainLoader->addLoader($defaultLoader);

        $translations = $chainLoader->load('en', 'admin/users');

        expect($translations)->toBe(['title' => 'Users']);
    });
});
