<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Support\Str;

/**
 * Parses translation group names, extracting namespace, subfolders, and JSON group identifiers.
 */
class TranslationGroupNameParser
{
    public function getJsonGroupName(): string
    {
        return (string) config('laravel-chained-translator.json_group', 'single');
    }

    public function isJsonGroup(string $group): bool
    {
        return $group === $this->getJsonGroupName();
    }

    /**
     * Pulls the namespace prefix (e.g. "vendor::") from a group string, mutating it in place.
     */
    public function pullNamespace(string &$group): ?string
    {
        if (!Str::contains($group, '::')) {
            return null;
        }

        $namespace = Str::before($group, '::');
        $group = Str::after($group, '::');

        return $namespace;
    }

    /**
     * Pulls the subfolders prefix from a group string, mutating it in place.
     */
    public function pullSubfolders(string &$group): ?string
    {
        if (!Str::contains($group, DIRECTORY_SEPARATOR)) {
            return null;
        }

        $subFolders = Str::beforeLast($group, DIRECTORY_SEPARATOR);
        $group = Str::afterLast($group, DIRECTORY_SEPARATOR);

        return $subFolders;
    }
}
