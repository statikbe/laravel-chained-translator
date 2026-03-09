<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Contracts\Translation\Loader;

/**
 * Chain of translation loaders
 */
class ChainLoader implements Loader
{
    /**
     * Loader instances of the chain
     *
     * @var array<int, Loader>
     */
    private array $loaders = [];

    /**
     * Add a translation loader to the chain
     *
     * @param Loader $loader
     * @param bool $prepend
     * @return void
     */
    public function addLoader(Loader $loader, $prepend = false): void
    {
        if ($prepend) {
            array_unshift($this->loaders, $loader);
            return;
        }

        $this->loaders[] = $loader;
    }

    /**
     * Removes the provided translation loader from the chain
     *
     * @param Loader $loader
     * @return bool True when removed, false otherwise
     */
    public function removeLoader(Loader $loader): bool
    {
        foreach ($this->loaders as $i => $l) {
            if ($l !== $loader) {
                continue;
            }

            unset($this->loaders[$i]);

            return true;
        }

        return false;
    }

    /**
     * Gets all the chained loaders
     *
     * @return array<int, Loader>
     */
    public function loaders(): array
    {
        return $this->loaders;
    }

    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        /** @var array<string, mixed> $messages */
        $messages = collect($this->loaders)
            ->reduce(
                static function (array $carry, Loader $loader) use ($locale, $group, $namespace): array {
                    /** @var array<string, mixed> */
                    return array_replace_recursive($loader->load($locale, $group, $namespace), $carry);
                },
                [],
            );

        return $messages;
    }

    /**
     * Add a new namespace to the loader.
     *
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function addNamespace($namespace, $hint): void
    {
        collect($this->loaders)->each(static function (Loader $loader) use ($namespace, $hint): void {
            $loader->addNamespace($namespace, $hint);
        });
    }

    /**
     * Add a new JSON path to the loader.
     *
     * @param  string  $path
     * @return void
     */
    public function addJsonPath($path): void
    {
        collect($this->loaders)->each(static function (Loader $loader) use ($path): void {
            $loader->addJsonPath($path);
        });
    }

    /**
     * Get an array of all the registered namespaces.
     *
     * @return array<string, mixed>
     */
    public function namespaces(): array
    {
        /** @var array<string, mixed> $namespaces */
        $namespaces = collect($this->loaders)
            ->reduce(
                static function (array $carry, Loader $loader): array {
                    /** @var array<string, mixed> */
                    return $carry + $loader->namespaces();
                },
                [],
            );

        return $namespaces;
    }
}
