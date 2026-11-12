<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when a locale or translation group would resolve to a file path
 * outside of the translation directory, or contains otherwise unsafe characters.
 */
class InvalidTranslationPathException extends InvalidArgumentException
{
    public static function invalidLocale(string $locale): self
    {
        return new self(sprintf('The locale "%s" is not a valid locale.', $locale));
    }

    public static function invalidGroup(string $group): self
    {
        return new self(sprintf('The translation group "%s" is not a valid translation group.', $group));
    }
}
