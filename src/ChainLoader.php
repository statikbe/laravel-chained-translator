<?php

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Contracts\Translation\Loader;

/**
 * Chain of translation loaders
 */
class ChainLoader implements Loader
{
    /**
     * Loader instances of the chain
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
        } else {
            $this->loaders[] = $loader;
        }
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
            if ($l === $loader) {
                unset($this->loaders[$i]);

                return true;
            }
        }

        return false;
    }

    /**
     * Gets all the chained loaders
     *
     * @return array
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
     * @return array
     */
    public function load($locale, $group, $namespace = null): array
    {
        $messages = [];

        foreach ($this->loaders as $loader) {
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
        foreach ($this->loaders as $loader) {
            $loader->addNamespace($namespace, $hint);
        }
    }

    /**
     * Add a new JSON path to the loader.
     *
     * @param  string  $path
     * @return void
     */
    public function addJsonPath($path): void
    {
        foreach ($this->loaders as $loader) {
            $loader->addJsonPath($path);
        }
    }

    /**
     * Get an array of all the registered namespaces.
     *
     * @return array
     */
    public function namespaces(): array
    {
        $namespaces = [];

        foreach ($this->loaders as $loader) {
            $namespaces += $loader->namespaces();
        }

        return $namespaces;
    }

}
