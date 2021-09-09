<?php

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\SplFileInfo;
use Brick\VarExporter\VarExporter;


class ChainedTranslationManager
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The default path for the loader.
     *
     * @var string
     */
    protected $path;

    /**
     * @var ChainLoader $translationLoader
     */
    private $translationLoader;

    /**
     * Create a new file loader instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param ChainLoader $translationLoader
     * @param string $path
     */
    public function __construct(Filesystem $files, ChainLoader $translationLoader, string $path)
    {
        $this->path = $path;
        $this->files = $files;
        $this->translationLoader = $translationLoader;
    }

    /**
     * Saves a translation
     *
     * @param string $locale
     * @param string $group
     * @param string $key
     * @param string $translation
     * @return void
     */
    public function save(string $locale, string $group, string $key, string $translation): void
    {
        if (! $this->localeFolderExists($locale)) {
            $this->createLocaleFolder($locale);
        }

        $translations = $this->getGroupTranslations($locale, $group);

        $translations->put($key, $translation);

        $this->saveGroupTranslations($locale, $group, $translations);
    }

    /**
     * Returns a list of translation groups. A translation group is the file name of the PHP files in the resources/lang
     * directory.
     * @return array
     */
    public function getTranslationGroups(): array
    {
        $groups = [];
        $langDirPath = resource_path('lang');
        $filesAndDirs = $this->files->allFiles($langDirPath);
        foreach ($filesAndDirs as $file) {
            /* @var SplFileInfo $file */
            if (!$file->isDir()) {
                $group = null;
                $vendorPath = strstr($file->getRelativePath(), 'vendor');
                $prefix = '';
                if ($vendorPath) {
                    $vendorPathParts = explode('vendor'.DIRECTORY_SEPARATOR, $vendorPath);
                    if(count($vendorPathParts) > 1){
                        $vendorPath = $vendorPathParts[1];
                    }
                    //remove locale from vendor path for php files, json files have the locale in the file name, eg. en.json
                    if (strtolower($file->getExtension()) === 'php') {
                        $vendorPath = substr($vendorPath, 0, strrpos($vendorPath, DIRECTORY_SEPARATOR));
                    }
                    $prefix = $vendorPath.'/';
                }
                if (strtolower($file->getExtension()) === 'php') {
                    $group = $prefix.$file->getFilenameWithoutExtension();
                } else {
                    if (strtolower($file->getExtension()) === 'json') {
                        if ($prefix) {
                            $group = $vendorPath;
                        } else {
                            $group = 'single';
                        }
                    }
                }
                if ($group) {
                    $groups[$group] = $group;
                }
            }
        }

        return array_values($groups);
    }

    public function getTranslationsForGroup(string $locale, string $group): array
    {
        if (Str::contains($group, '/')){
            [$namespace, $group] = explode('/', $group);
        }

        if ($group === 'single') {
            return $this->compressHierarchicalTranslationsToDotNotation($this->translationLoader->load($locale, '*', '*'));
        } else {
            return $this->compressHierarchicalTranslationsToDotNotation($this->translationLoader->load($locale, $group, $namespace ?? null));
        }
    }

    public function mergeChainedTranslationsIntoDefaultTranslations(string $locale): void {
        $defaultLangPath = App::get('path.lang');
        if (! $this->localeFolderExists($locale)) {
            $this->createLocaleFolder($locale);
        }
        $groups = $this->getTranslationGroups();

        foreach($groups as $group){
            if (Str::contains($group, '/')){
                [$namespace, $groupWithoutNamespace] = explode('/', $group);
            }

            if ($group === 'single') { //Preparation for then json support is added
                $translations = collect($this->translationLoader->load($locale, '*', '*'));
            } else {
                $translations = collect($this->translationLoader->load($locale, $groupWithoutNamespace ?? $group, $namespace ?? null));
            }

            if ($translations->isNotEmpty()){
                $this->saveGroupTranslations($locale, $group, $translations, $defaultLangPath);
            }
        }
    }

    private function compressHierarchicalTranslationsToDotNotation(array $translations): array
    {
        $iteratorIterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($translations));
        $result = [];
        foreach ($iteratorIterator as $leafValue) {
            $keys = [];
            foreach (range(0, $iteratorIterator->getDepth()) as $depth) {
                $keys[] = $iteratorIterator->getSubIterator($depth)->key();
            }
            $result[ join('.', $keys) ] = $leafValue;
        }
        return $result;
    }

    private function localeFolderExists(string $locale): bool
    {
        return $this->files->exists($this->path.DIRECTORY_SEPARATOR.$locale);
    }

    private function createLocaleFolder(string $locale): bool
    {
        return $this->files->makeDirectory($this->path.DIRECTORY_SEPARATOR.$locale, 0755, true);
    }

    private function getGroupTranslations(string $locale, string $group): Collection
    {
        $groupPath = $this->getGroupPath($locale, $group);

        if($this->files->exists($groupPath)){
            return collect($this->files->getRequire($groupPath));
        }

        return collect([]);
    }

    private function saveGroupTranslations(string $locale, string $group, Collection $translations, string $languagePath=null): void
    {
        // here we check if it's a namespaced translation which need saving to a
        // different path
        $translations = $translations->toArray();
        $translations = array_undot($translations);
        ksort($translations);

        $groupPath = $this->getGroupPath($locale, $group, $languagePath);
        $this->files->put($groupPath, "<?php\n\nreturn " . VarExporter::export($translations) . ';' . \PHP_EOL);

        // clear the opcache of the group file, because otherwise in the next request, an old cached file can be read in
        // and the saved translation can be overwritten...
        if(function_exists('opcache_invalidate')) {
            opcache_invalidate($groupPath, true);
        }
    }

    private function getGroupPath(string $locale, string $group, string $languagePath=null): string
    {
        $basePath = $this->getGroupBasePath($locale, $group, $languagePath);

        if (Str::contains($group, '/')){
            [$namespace, $group] = explode('/', $group);
        }

        return $basePath.DIRECTORY_SEPARATOR.$group.'.php';
    }

    private function getGroupBasePath(string $locale, string $group, string $languagePath=null): string
    {
        $languagePath = ($languagePath ?? $this->path);
        if (Str::contains($group, '/')){
            [$namespace, $group] = explode('/', $group);

            $groupBasePath = $languagePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$namespace.DIRECTORY_SEPARATOR.$locale;
            $this->createDirectory($groupBasePath);
            return $groupBasePath;
        }

        $groupBasePath = $languagePath.DIRECTORY_SEPARATOR.$locale;

        //create directory if not exists:
        $this->createDirectory($groupBasePath);

        return $groupBasePath;
    }

    private function createDirectory(string $path): void
    {
        if(!$this->files->exists($path)){
            $this->files->makeDirectory($path,  0755, true);
        }
    }
}
