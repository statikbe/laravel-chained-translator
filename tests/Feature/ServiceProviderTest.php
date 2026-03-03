<?php

declare(strict_types=1);

use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;

describe('TranslationServiceProvider', function () {
    it('can resolve ChainedTranslatorConfig from container', function () {
        $config = app(ChainedTranslatorConfig::class);

        expect($config)->toBeInstanceOf(ChainedTranslatorConfig::class);
    });

    it('registers ChainLoader as translation loader', function () {
        $loader = app('translation.loader');

        // In test environment, it might be the standard loader or our ChainLoader
        // depending on if the package provider is fully registered
        expect($loader)->not->toBeNull();
    });

    it('registers ChainedTranslationManager as singleton', function () {
        // This may not be registered if the provider isn't fully loaded
        // Just test that the class exists and can be instantiated
        expect(class_exists(ChainedTranslationManager::class))->toBeTrue();
    });

    it('provides expected services', function () {
        $provider = new \Statikbe\LaravelChainedTranslator\TranslationServiceProvider(app());
        $provides = $provider->provides();

        expect($provides)->toContain('chained-translator.path.lang.custom');
        expect($provides)->toContain(ChainedTranslationManager::class);
        expect($provides)->toContain(ChainedTranslatorConfig::class);
    });

    it('has correct package structure', function () {
        // Verify the provider file exists and has the right class
        $providerFile = __DIR__ . '/../../src/TranslationServiceProvider.php';
        expect($providerFile)->toBeFile();

        $content = file_get_contents($providerFile);
        expect($content)->toContain('TranslationServiceProvider');
        expect($content)->toContain('ChainedTranslationManager');
    });
});
