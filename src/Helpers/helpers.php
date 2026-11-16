<?php

declare(strict_types=1);

use Statikbe\LaravelChainedTranslator\TranslationLines;

if (!function_exists('ct_array_undot')) {
    /**
     * Places the dotted keys of a list of translations inside their nested arrays, e.g. ['a.b' => 'x'] becomes
     * ['a' => ['b' => 'x']]. See TranslationLines::undot().
     *
     * @param array<string, mixed> $dotNotationArray
     * @return array<string, mixed>
     */
    function ct_array_undot(array $dotNotationArray): array
    {
        /** @var array<string, mixed> */
        return TranslationLines::undot($dotNotationArray);
    }
}
