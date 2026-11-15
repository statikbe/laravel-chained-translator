<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Translation\TranslationServiceProvider;
use ReflectionClass;

/**
 * Locates the translations Laravel ships itself (validation, auth, pagination, passwords).
 *
 * Since Laravel 11 these live in Illuminate/Translation/lang instead of the app's lang directory,
 * which then only holds the lines the app overrides.
 */
final class FrameworkLangPath
{
    /**
     * The framework's lang directory, or null on Laravel versions that do not ship one.
     */
    public static function resolve(): ?string
    {
        $path = dirname((string) (new ReflectionClass(TranslationServiceProvider::class))->getFileName()) . '/lang';

        return is_dir($path) ? $path : null;
    }
}
