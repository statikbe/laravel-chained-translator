<?php

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;

class CustomLangLoader extends FileLoader
{
    public function __construct(Filesystem $files, $path)
    {
        parent::__construct($files, $path);
    }

    protected function loadNamespaced($locale, $group, $namespace)
    {
        if(isset($this->hints[$namespace])) {
            return $this->loadNamespaceOverrides([], $locale, $group, $namespace);
        }

        return [];
    }
}