<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException;

class ChainedTranslationManager
{
    private readonly TranslationGroupFinder $groupFinder;
    private readonly TranslationFileWriter $fileWriter;
    private readonly TranslationGroupNameParser $nameParser;

    /**
     * The filesystem instance.
     *
     * @deprecated Use TranslationFileWriter instead. Kept for backward compatibility.
     */
    protected Filesystem $files;

    /**
     * The custom language path.
     *
     * @deprecated Use TranslationFileWriter instead. Kept for backward compatibility.
     */
    protected string $path;

    /**
     * Create a new ChainedTranslationManager instance.
     */
    public function __construct(
        Filesystem $files,
        private readonly ChainLoader $translationLoader,
        string $path,
        ?ChainedTranslatorConfig $config = null,
    ) {
        // Keep references for backward compatibility with subclasses
        $this->files = $files;
        $this->path = $path;

        $config ??= new ChainedTranslatorConfig();
        $this->nameParser = new TranslationGroupNameParser($config);
        $this->groupFinder = new TranslationGroupFinder($files, $this->nameParser);
        $this->fileWriter = new TranslationFileWriter($files, $this->nameParser, $path, $config);
    }

    /**
     * Saves a translation.
     *
     * @throws SaveTranslationFileException
     */
    public function save(string $locale, string $group, string $key, string $translation): void
    {
        $translations = $this->getCustomTranslations($locale, $group);
        $translations->put($key, $translation);
        $this->fileWriter->writeGroupTranslations($locale, $group, $translations);
    }

    /**
     * Returns a list of translation groups (file names of PHP files in the lang directory).
     *
     * @return array<int, string>
     */
    public function getTranslationGroups(): array
    {
        return $this->groupFinder->findAll();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTranslationsForGroup(string $locale, string $group): array
    {
        if ($this->nameParser->isJsonGroup($group)) {
            return $this->compressHierarchicalTranslationsToDotNotation($this->translationLoader->load(
                $locale,
                '*',
                '*',
            ));
        }

        $namespacedGroup = $group;
        $namespace = $this->nameParser->pullNamespace($namespacedGroup);

        return $this->compressHierarchicalTranslationsToDotNotation($this->translationLoader->load(
            $locale,
            $namespacedGroup,
            $namespace,
        ));
    }

    /**
     * @throws SaveTranslationFileException
     */
    public function mergeChainedTranslationsIntoDefaultTranslations(string $locale): void
    {
        $defaultLangPath = function_exists('lang_path') ? lang_path() : resource_path('lang');

        if (!$this->fileWriter->localeFolderExists($locale)) {
            $this->fileWriter->createLocaleFolder($locale);
        }

        foreach ($this->groupFinder->findAll() as $group) {
            $translations = $this->loadTranslationsForGroup($locale, $group);

            if ($translations->isNotEmpty()) {
                $this->fileWriter->writeGroupTranslations($locale, $group, $translations, $defaultLangPath);
            }
        }
    }

    /**
     * @return Collection<string, mixed>
     */
    public function getCustomTranslations(string $locale, string $group): Collection
    {
        return $this->fileWriter->readGroupTranslations($locale, $group);
    }

    /**
     * @return Collection<string, mixed>
     */
    private function loadTranslationsForGroup(string $locale, string $group): Collection
    {
        if ($this->nameParser->isJsonGroup($group)) {
            return collect($this->translationLoader->load($locale, '*', '*'));
        }

        $namespacedGroup = $group;
        $namespace = $this->nameParser->pullNamespace($namespacedGroup);

        return collect($this->translationLoader->load($locale, $namespacedGroup, $namespace));
    }

    /**
     * @param array<string, mixed> $translations
     * @return array<string, mixed>
     */
    private function compressHierarchicalTranslationsToDotNotation(array $translations): array
    {
        $iteratorIterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($translations));
        $result = [];
        // @mago-expect analysis:mixed-assignment
        foreach ($iteratorIterator as $leafValue) {
            /** @var array<int, string> $keys */
            $keys = [];
            foreach (range(0, $iteratorIterator->getDepth()) as $depth) {
                $subIterator = $iteratorIterator->getSubIterator($depth);
                if ($subIterator !== null) {
                    $keys[] = (string) $subIterator->key();
                }
            }
            $result[implode('.', $keys)] = $leafValue;
        }

        return $result;
    }
}
