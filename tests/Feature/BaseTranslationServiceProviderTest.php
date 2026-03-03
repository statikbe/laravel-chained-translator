<?php

declare(strict_types=1);

describe('BaseTranslationServiceProvider', function () {
    it('provides expected services', function () {
        $provider = new \Statikbe\LaravelChainedTranslator\BaseTranslationServiceProvider(app());
        $provides = $provider->provides();

        expect($provides)->toContain('translation.loader.custom');
        expect($provides)->toContain('translation.loader.default');
        expect($provides)->toContain('translation.manager');
    });

    it('has correct class structure', function () {
        $providerFile = __DIR__ . '/../../src/BaseTranslationServiceProvider.php';
        expect($providerFile)->toBeFile();

        $content = file_get_contents($providerFile);
        expect($content)->toContain('BaseTranslationServiceProvider');
        expect($content)->toContain('registerLoader');
        expect($content)->toContain('ChainLoader');
    });
});
