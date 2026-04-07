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
});
