<?php

namespace Statikbe\LaravelChainedTranslator;

use Brick\VarExporter\ExportException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException;
use Symfony\Component\Finder\SplFileInfo;
use Brick\VarExporter\VarExporter;


class ChainedTranslationManager
{
    protected Filesystem $files;

    /**
     * The default path for the loader.
     */
    protected string $path;
    private ChainLoader $translationLoader;

    /**
     * Create a new file loader instance.
     *
     * @param Filesystem $files
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
     * @throws SaveTranslationFileException
     */
    public function save(string $locale, string $group, string $key, string $translation): void
    {
        $translations = $this->getCustomTranslations($locale, $group);

        $translations->put($key, $translation);

        $this->saveGroupTranslations($locale, $group, $translations);
    }

    /**
     * Returns a list of translation groups. A translation group is the file name of the PHP files in the lang
     * directory.
     * @return array
     */
    public function getTranslationGroups(): array
    {
        $groups = [];
        $langDirPath = function_exists('lang_path') ? lang_path() : resource_path('lang');
        $filesAndDirs = $this->files->allFiles($langDirPath);
        foreach ($filesAndDirs as $file) {
            /* @var SplFileInfo $file */
            if (!$file->isDir()) {
                $relativePath = $file->getRelativePath();
                $group = null;
                $prefix = null;
                $subFolders = null;
                $vendorPath = strstr($relativePath, 'vendor');

                if ($vendorPath) {
                    $namespace = null;
                    $vendorPath = Str::replaceFirst('vendor'.DIRECTORY_SEPARATOR, null, $vendorPath);

                    //remove locale from vendor path for php files, json files have the locale in the file name, eg. en.json
                    if (strtolower($file->getExtension()) === 'php') {
                        $options = explode(DIRECTORY_SEPARATOR, $vendorPath);
                        $namespace = $options[0];
                        unset($options[0]);
                        unset($options[1]);
                        $subFolders = implode(DIRECTORY_SEPARATOR, array_filter($options));
                    }

                    $prefix = $namespace.'::'.$prefix;
                } else {
                    if (strtolower($file->getExtension()) === 'php') {
                        $options = explode(DIRECTORY_SEPARATOR, $relativePath);
                        unset($options[0]);
                        $subFolders = implode(DIRECTORY_SEPARATOR, array_filter($options));
                    }
                }
                if (strtolower($file->getExtension()) === 'php') {
                    $group = $prefix.implode(DIRECTORY_SEPARATOR, array_filter([$subFolders, $file->getFilenameWithoutExtension()]));
                } else {
                    if (strtolower($file->getExtension()) === 'json') {
                        $group = $this->getJsonGroupName();
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
        $namespace = $this->pullNamespaceFromGroup($group);

        if ($group === $this->getJsonGroupName()) {
            return $this->compressHierarchicalTranslationsToDotNotation($this->translationLoader->load($locale, '*', '*'));
        }

        return $this->compressHierarchicalTranslationsToDotNotation($this->translationLoader->load($locale, $group, $namespace ?? null));
    }

    /**
     * @throws SaveTranslationFileException
     */
    public function mergeChainedTranslationsIntoDefaultTranslations(string $locale): void {
        $defaultLangPath = function_exists('lang_path') ? lang_path() : resource_path('lang');
        if (! $this->localeFolderExists($locale)) {
            $this->createLocaleFolder($locale);
        }
        $groups = $this->getTranslationGroups();

        foreach($groups as $group) {
            $groupWithNamespace = $group;
            $namespace = $this->pullNamespaceFromGroup($group);

            if ($group === $this->getJsonGroupName()) {
                $translations = collect($this->translationLoader->load($locale, '*', '*'));
            } else {
                $translations = collect($this->translationLoader->load($locale,  $group, $namespace ?? null));
            }

            if ($translations->isNotEmpty()) {
                $this->saveGroupTranslations($locale, $groupWithNamespace, $translations, $defaultLangPath);
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

    public function getCustomTranslations(string $locale, string $group): Collection
    {
        $groupPath = $this->getGroupPath($locale, $group);

        if($this->files->exists($groupPath)) {
            if ($group === $this->getJsonGroupName()){
                return collect(json_decode(file_get_contents($groupPath)));
            }
            return collect($this->files->getRequire($groupPath));
        }

        return collect([]);
    }

    /**
     * @throws SaveTranslationFileException
     */
    private function saveGroupTranslations(string $locale, string $group, Collection $translations, string $languagePath=null): void
    {
        $groupPath = $this->getGroupPath($locale, $group, $languagePath);
        $translations = $translations->toArray();

        if ($group === $this->getJsonGroupName()){
            ksort($translations);

            $contents = json_encode($translations, JSON_PRETTY_PRINT);
        } else {
            //Decide if dotted keys should stay or should be grouped into arrays
            if (config('laravel-chained-translator.group_keys_in_array', true)){
                $translations = array_undot($translations);
            }
            ksort($translations);

            try {
                $contents = "<?php\n\nreturn " . VarExporter::export($translations) . ';' . \PHP_EOL;
            }
            catch(ExportException $ex){
                throw new SaveTranslationFileException('The translations could not be transformed to the .php translation file.', 0, $ex);
            }
        }

        $success = $this->files->put($groupPath, $contents);

        if(!$success){
            throw new SaveTranslationFileException("The translation file $groupPath could not be saved.");
        }

        // clear the opcache of the group file, because otherwise in the next request, an old cached file can be read in
        // and the saved translation can be overwritten...
        if(function_exists('opcache_invalidate')) {
            opcache_invalidate($groupPath, true);
        }
    }

    private function getGroupPath(string $locale, string $group, string $languagePath=null): string
    {
        if ($group === $this->getJsonGroupName()) {
            return ($languagePath ?? $this->path).DIRECTORY_SEPARATOR.$locale.'.json';
        }

        $basePath = $this->getGroupBasePath($locale, $group, $languagePath);

        $this->pullNamespaceFromGroup($group);
        $this->pullSubfoldersFromGroup($group);

        return $basePath.DIRECTORY_SEPARATOR.$group.'.php';
    }

    private function getGroupBasePath(string $locale, string $group, string $languagePath=null): string
    {
        $languagePath = ($languagePath ?? $this->path);

        $namespace = $this->pullNamespaceFromGroup($group);
        if ($namespace){
            $namespace = 'vendor'.DIRECTORY_SEPARATOR.$namespace;
        }
        $subFolders = $this->pullSubfoldersFromGroup($group);

        $groupBasePath = implode(DIRECTORY_SEPARATOR, array_filter([$languagePath, $namespace, $locale, $subFolders]));

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

    private function pullNamespaceFromGroup(string &$group): ?string
    {
        $namespace = null;

        if (Str::contains($group, '::')) {
            $namespace = Str::before($group, '::');
            $group = Str::after($group, '::');
        }

        return $namespace;
    }

    private function pullSubfoldersFromGroup(string &$group): ?string
    {
        $subFolders = null;
        if (Str::contains($group, DIRECTORY_SEPARATOR)){
            $subFolders = Str::beforeLast($group, DIRECTORY_SEPARATOR);
            $group = Str::afterLast($group, DIRECTORY_SEPARATOR);
        }

        return $subFolders;
    }

    /**
     * @throws SaveTranslationFileException
     */
    private function saveJson(string $locale, string $key, string $translation): void
    {
        $jsonGroup = $this->getJsonGroupName();
        $translations = $this->getCustomTranslations($locale, $jsonGroup);

        $translations->put($key, $translation);

        $this->saveGroupTranslations($locale, $jsonGroup, $translations);
    }

    private function getJsonGroupName(): string
    {
        return config('laravel-chained-translator.json_group', 'single');
    }
}
