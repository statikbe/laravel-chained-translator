<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator\Facades;

use Illuminate\Support\Facades\Facade;
use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
use Statikbe\LaravelChainedTranslator\ChainedTranslatorConfig;

/**
 * @method static ChainedTranslatorConfig config()
 * @method static void save(string $locale, string $group, string $key, string $translation)
 * @method static array<int, string> getTranslationGroups()
 * @method static array<string, mixed> getTranslationsForGroup(string $locale, string $group)
 * @method static void mergeChainedTranslationsIntoDefaultTranslations(string $locale)
 *
 * @see ChainedTranslationManager
 */
class ChainedTranslator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChainedTranslationManager::class;
    }
}
