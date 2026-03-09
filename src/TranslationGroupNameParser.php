<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Support\Str;

/**
 * Parses translation group names, extracting namespace, subfolders, and JSON group identifiers.
 */
class TranslationGroupNameParser
{
    private readonly ChainedTranslatorConfig $config;

    public function __construct(?ChainedTranslatorConfig $config = null)
    {
        $this->config = $config ?? new ChainedTranslatorConfig();
    }

    public function getJsonGroupName(): string
    {
        return $this->config->getJsonGroupName();
    }

    public function isJsonGroup(string $group): bool
    {
        return $group === $this->getJsonGroupName();
    }

    /**
     * Extracts the namespace prefix (e.g. "vendor::") from a group string.
     *
     * Returns a tuple of [namespace|null, remaining group string].
     *
     * @return array{0: string|null, 1: string}
     */
    public function extractNamespace(string $group): array
    {
        if (!Str::contains($group, '::')) {
            return [null, $group];
        }

        return [Str::before($group, '::'), Str::after($group, '::')];
    }

    /**
     * Extracts the subfolders prefix from a group string.
     *
     * Returns a tuple of [subfolders|null, remaining group string].
     *
     * @return array{0: string|null, 1: string}
     */
    public function extractSubfolders(string $group): array
    {
        if (!Str::contains($group, DIRECTORY_SEPARATOR)) {
            return [null, $group];
        }

        return [Str::beforeLast($group, DIRECTORY_SEPARATOR), Str::afterLast($group, DIRECTORY_SEPARATOR)];
    }
}
