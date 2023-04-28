<?php

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;

class NonPackageFileLoader extends FileLoader
{
    protected function loadNamespaced($locale, $group, $namespace)
    {
        if(isset($this->hints[$namespace])) {
            //We removed the line from FileLoader that loads the translations from the packages in the /vendor folder.
            //This is to avoid overwriting published vendor translations done in the default /lang/vendor folder,
            //with the translations provided by the packages themselves in /vendor.
            return $this->loadNamespaceOverrides([], $locale, $group, $namespace);
        }

        return [];
    }
}