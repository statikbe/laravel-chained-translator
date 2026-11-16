<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Combines translation lines whose keys can be nested arrays, dotted keys or both.
 *
 * Custom lines are saved with dotted keys, e.g. 'attributes.email', while app files are usually nested, e.g.
 * 'attributes' => ['email' => ...]. Laravel finds a dotted key when asked for 'validation.attributes.email',
 * but not when asked for the whole 'validation.attributes' array, as the validator does. So a dotted key is
 * placed inside the nested arrays, and into the longest key that already exists: a custom line
 * 'custom.items.*.name.required' goes into 'custom' => ['items.*.name' => ['required' => ...]].
 */
final class TranslationLines
{
    /**
     * Places the dotted keys of the lines inside their nested arrays. A dotted key overrides the nested
     * line it points to, as Laravel looks up dotted keys first.
     *
     * @param array<array-key, mixed> $lines
     * @return array<array-key, mixed>
     */
    public static function undot(array $lines): array
    {
        $dotted = array_filter($lines, self::isDotted(...), ARRAY_FILTER_USE_KEY);

        return self::merge(array_diff_key($lines, $dotted), $dotted);
    }

    /**
     * Lays the overriding lines over the lines, placing each key in the existing nested arrays.
     *
     * A key stays as it is when it cannot be split into segments (see isSplittable()), or when one of its
     * parents is a translation line itself, e.g. 'between.numeric' when 'between' is a string.
     *
     * @param array<array-key, mixed> $lines
     * @param array<array-key, mixed> $overrides
     * @return array<array-key, mixed>
     */
    public static function merge(array $lines, array $overrides): array
    {
        /** @var mixed $value */
        foreach ($overrides as $key => $value) {
            $lines = self::place($lines, $key, $value);
        }

        return $lines;
    }

    /**
     * @param array<array-key, mixed> $lines
     * @return array<array-key, mixed>
     */
    private static function place(array $lines, int|string $key, mixed $value): array
    {
        if (array_key_exists($key, $lines)) {
            $lines[$key] = self::override($lines[$key], $value);

            return $lines;
        }

        $leaves = self::leavesBelongingInDottedKeys($lines, $key, $value);

        return $leaves !== null ? self::merge($lines, $leaves) : self::placeNew($lines, $key, $value);
    }

    /**
     * The lines of a nested array as dotted keys, when they belong in existing keys with dots, e.g.
     * 'items' => ['*' => ['name' => ...]] belongs in 'items.*.name' => [...]. Otherwise null.
     *
     * @param array<array-key, mixed> $lines
     * @return array<string, mixed>|null
     */
    private static function leavesBelongingInDottedKeys(array $lines, int|string $key, mixed $value): ?array
    {
        $hasDottedKeysUnder = collect(array_keys($lines))
            ->contains(static fn(int|string $line): bool => str_starts_with((string) $line, $key . '.'));

        if (!is_array($value) || !$hasDottedKeysUnder) {
            return null;
        }

        /** @var array<string, mixed> $leaves */
        $leaves = Arr::dot($value, $key . '.');

        return collect(array_keys($leaves))->every(self::isSplittable(...)) ? $leaves : null;
    }

    /**
     * Places a key that is not in the lines yet: a dotted key in the longest existing key, or nested.
     *
     * @param array<array-key, mixed> $lines
     * @return array<array-key, mixed>
     */
    private static function placeNew(array $lines, int|string $key, mixed $value): array
    {
        $parent = self::parentToPlaceIn($lines, $key);

        if ($parent === null) {
            $lines[$key] = $value;

            return $lines;
        }

        /** @var array<array-key, mixed> $parentLines */
        $parentLines = $lines[$parent] ?? [];
        $lines[$parent] = self::place($parentLines, Str::after((string) $key, $parent . '.'), $value);

        return $lines;
    }

    /**
     * The key to place a dotted key in: the longest existing key it starts with, e.g. 'items.*.name' before
     * 'items', or else its first segment. Null when the key stays as it is: it is not dotted, or nesting
     * would replace a translation line.
     *
     * @param array<array-key, mixed> $lines
     */
    private static function parentToPlaceIn(array $lines, int|string $key): ?string
    {
        if (!self::isDotted($key)) {
            return null;
        }

        $parent = self::longestExistingParent($lines, (string) $key) ?? Str::before((string) $key, '.');

        return !array_key_exists($parent, $lines) || is_array($lines[$parent]) ? $parent : null;
    }

    /**
     * An array line is merged with an overriding array, any other line is replaced.
     */
    private static function override(mixed $line, mixed $value): mixed
    {
        return is_array($line) && is_array($value) ? self::merge($line, $value) : $value;
    }

    /**
     * The longest key in the lines that the dotted key starts with, e.g. 'items.*.name' for
     * 'items.*.name.required', or null when there is none.
     *
     * @param array<array-key, mixed> $lines
     */
    private static function longestExistingParent(array $lines, string $key): ?string
    {
        $parent = $key;

        while (str_contains($parent, '.')) {
            $parent = Str::beforeLast($parent, '.');

            if (array_key_exists($parent, $lines)) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Whether the key can be split into nested keys and has a dot to split on.
     */
    private static function isDotted(int|string $key): bool
    {
        return str_contains((string) $key, '.') && self::isSplittable($key);
    }

    /**
     * Whether the key can be split on its dots. Not when a dot is followed by a space, as the key is likely
     * a sentence, e.g. "Saved. Thanks.", or when a segment is empty, e.g. "etc.".
     */
    private static function isSplittable(int|string $key): bool
    {
        return !str_contains((string) $key, '. ') && !in_array('', explode('.', (string) $key), true);
    }
}
