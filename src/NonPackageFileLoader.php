<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Translation\FileLoader;

class NonPackageFileLoader extends FileLoader
{
    /**
     * @param string $locale
     * @param string $group
     * @param string $namespace
     * @return array<string, mixed>
     */
    protected function loadNamespaced($locale, $group, $namespace): array
    {
        if (array_key_exists($namespace, $this->hints)) {
            //We removed the line from FileLoader that loads the translations from the packages in the /vendor folder.
            //This is to avoid overwriting published vendor translations done in the default /lang/vendor folder,
            //with the translations provided by the packages themselves in /vendor.
            // @mago-expect analysis:less-specific-return-statement
            return $this->loadNamespaceOverrides([], $locale, $group, $namespace);
        }

        return [];
    }
}
