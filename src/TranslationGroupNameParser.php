<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Support\Str;
use Statikbe\LaravelChainedTranslator\Exceptions\InvalidTranslationPathException;

/**
 * Parses translation group names, extracting namespace, subfolders, and JSON group identifiers.
 */
class TranslationGroupNameParser
{
    /**
     * A locale is used as a single directory or file name, e.g. "en", "en_US" or "pt-BR".
     */
    private const LOCALE_PATTERN = '/^[A-Za-z0-9_-]+$/D';

    /**
     * A namespace, subfolder or group file name. Must not start with a dot, which excludes "." and "..".
     */
    private const PATH_SEGMENT_PATTERN = '/^[\p{L}\p{N}_-][\p{L}\p{N}_.-]*$/uD';

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
        if (!Str::contains($group, '/')) {
            return [null, $group];
        }

        return [Str::beforeLast($group, '/'), Str::afterLast($group, '/')];
    }

    /**
     * Ensures the locale can safely be used as a single path segment.
     *
     * @throws InvalidTranslationPathException
     */
    public function assertValidLocale(string $locale): void
    {
        if (preg_match(self::LOCALE_PATTERN, $locale) !== 1) {
            throw InvalidTranslationPathException::invalidLocale($locale);
        }
    }

    /**
     * Ensures the group (optionally namespaced and with subfolders) only consists of safe path segments,
     * so it cannot resolve to a file outside the translation directory.
     *
     * @throws InvalidTranslationPathException
     */
    public function assertValidGroup(string $group): void
    {
        if ($this->isJsonGroup($group)) {
            return;
        }

        [$namespace, $groupPath] = $this->extractNamespace($group);
        $segments = explode('/', $groupPath);

        if ($namespace !== null) {
            $segments[] = $namespace;
        }

        foreach ($segments as $segment) {
            if (preg_match(self::PATH_SEGMENT_PATTERN, $segment) !== 1) {
                throw InvalidTranslationPathException::invalidGroup($group);
            }
        }
    }
}
