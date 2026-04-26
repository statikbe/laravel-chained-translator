<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use ReflectionClass;

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
     * Aggregated translation paths from inner loaders.
     *
     * Exposed (non-private) so tools that introspect Laravel's translation
     * loader via reflection (e.g. barryvdh/laravel-ide-helper's translations
     * template) can discover the search paths.
     *
     * @var array<int, string>
     */
    protected array $paths = [];

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
        } else {
            $this->loaders[] = $loader;
        }

        $this->refreshPaths();
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
            $this->refreshPaths();

            return true;
        }

        return false;
    }

    /**
     * Aggregated translation paths from all inner loaders.
     *
     * @return array<int, string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Recompute the aggregated paths from inner loaders.
     */
    private function refreshPaths(): void
    {
        $paths = [];

        foreach ($this->loaders as $loader) {
            if (method_exists($loader, 'paths')) {
                /** @var array<int, string>|string $value */
                $value = $loader->paths();
                $paths = array_merge($paths, Arr::wrap($value));

                continue;
            }

            $reflection = new ReflectionClass($loader);

            if ($reflection->hasProperty('paths')) {
                /** @var array<int, string>|string $value */
                $value = $reflection->getProperty('paths')->getValue($loader);
                $paths = array_merge($paths, Arr::wrap($value));

                continue;
            }

            if ($reflection->hasProperty('path')) {
                /** @var array<int, string>|string $value */
                $value = $reflection->getProperty('path')->getValue($loader);
                $paths = array_merge($paths, Arr::wrap($value));
            }
        }

        $this->paths = array_values(array_unique(array_filter($paths, 'is_string')));
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
        $messages = [];

        foreach ($this->loaders as $loader) {
            /** @var array<string, mixed> $messages */
            $messages = array_replace_recursive($loader->load($locale, $group, $namespace), $messages);
        }

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
        $namespaces = [];

        foreach ($this->loaders as $loader) {
            /** @var array<string, mixed> $namespaces */
            $namespaces += $loader->namespaces();
        }

        return $namespaces;
    }
}
