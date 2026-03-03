<?php

declare(strict_types=1);

use Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException;

describe('SaveTranslationFileException', function () {
    it('can be thrown', function () {
        expect(function () {
            throw new SaveTranslationFileException('Test error');
        })->toThrow(SaveTranslationFileException::class, 'Test error');
    });

    it('is an instance of Exception', function () {
        $exception = new SaveTranslationFileException('Test');

        expect($exception)->toBeInstanceOf(\Exception::class);
    });

    it('can have a custom message', function () {
        $message = 'Custom error message';
        $exception = new SaveTranslationFileException($message);

        expect($exception->getMessage())->toBe($message);
    });

    it('can have a previous exception', function () {
        $previous = new \RuntimeException('Original error');
        $exception = new SaveTranslationFileException('Wrapper', 0, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    it('can have a custom code', function () {
        $exception = new SaveTranslationFileException('Test', 500);

        expect($exception->getCode())->toBe(500);
    });
});
