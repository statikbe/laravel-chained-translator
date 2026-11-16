<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\LockableFile;
use Illuminate\Support\Collection;
use Illuminate\Translation\FileLoader;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Statikbe\LaravelChainedTranslator\Exceptions\InvalidTranslationPathException;
use Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException;

class ChainedTranslationManager
{
    /**
     * The file in the custom lang directory that saves lock, so they do not overwrite each other.
     */
    private const LOCK_FILE = '.lock';

    private readonly TranslationGroupFinder $groupFinder;
    private readonly TranslationFileWriter $fileWriter;
    private readonly TranslationGroupNameParser $nameParser;
    private readonly Filesystem $filesystem;
    private readonly string $customLangPath;

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
        // @mago-expect analysis:deprecated-property
        $this->files = $files;
        // @mago-expect analysis:deprecated-property
        $this->path = $path;

        $this->filesystem = $files;
        $this->customLangPath = $path;
        $config ??= new ChainedTranslatorConfig();
        $this->nameParser = new TranslationGroupNameParser($config);
        $this->groupFinder = new TranslationGroupFinder($files, $this->nameParser);
        $this->fileWriter = new TranslationFileWriter($files, $this->nameParser, $path, $config);
    }

    /**
     * Returns the package configuration accessor.
     */
    public function config(): ChainedTranslatorConfig
    {
        return $this->fileWriter->getConfig();
    }

    /**
     * Saves a translation.
     *
     * @throws SaveTranslationFileException
     */
    public function save(string $locale, string $group, string $key, string $translation): void
    {
        // Saving rewrites the whole file. The lock makes a save in another request wait until this one is
        // written, so it reads this translation instead of dropping it.
        $lock = new LockableFile($this->customLangPath . DIRECTORY_SEPARATOR . self::LOCK_FILE, 'c');

        try {
            $lock->getExclusiveLock(block: true);
            $translations = $this->getCustomTranslations($locale, $group);
            $translations->put($key, $translation);
            $this->fileWriter->writeGroupTranslations($locale, $group, $translations);
        } finally {
            $lock->close();
        }
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
     * @throws InvalidTranslationPathException
     */
    public function getTranslationsForGroup(string $locale, string $group): array
    {
        // The loaders build file paths from the locale and group, so validate them before loading.
        $this->nameParser->assertValidLocale($locale);
        $this->nameParser->assertValidGroup($group);

        if ($this->nameParser->isJsonGroup($group)) {
            return $this->compressHierarchicalTranslationsToDotNotation($this->translationLoader->load(
                $locale,
                '*',
                '*',
            ));
        }

        [$namespace, $groupName] = $this->nameParser->extractNamespace($group);

        return $this->compressHierarchicalTranslationsToDotNotation($this->translationLoader->load(
            $locale,
            $groupName,
            $namespace,
        ));
    }

    /**
     * @throws SaveTranslationFileException
     */
    public function mergeChainedTranslationsIntoDefaultTranslations(string $locale): void
    {
        $this->nameParser->assertValidLocale($locale);
        $defaultLangPath = lang_path();

        // Also the groups that only have custom translations, e.g. JSON translations in an app without a lang directory.
        $groups = array_unique([
            ...$this->groupFinder->findAll(),
            ...$this->groupFinder->findIn([$this->customLangPath]),
        ]);

        foreach ($groups as $group) {
            $translations = $this->loadTranslationsForGroup($locale, $group, $defaultLangPath);

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
     * Loads the translations of a group that merging writes to the default lang directory.
     *
     * @return Collection<string, mixed>
     */
    private function loadTranslationsForGroup(string $locale, string $group, string $defaultLangPath): Collection
    {
        if ($this->nameParser->isJsonGroup($group)) {
            return collect($this->translationLoader->load($locale, '*', '*'));
        }

        [$namespace, $groupName] = $this->nameParser->extractNamespace($group);

        if ($namespace !== null) {
            return collect($this->translationLoader->load($locale, $groupName, $namespace));
        }

        // Only the lines the app owns: its own lang file with the custom lines placed inside its nested arrays
        // (see TranslationLines), so each line is in the file once. Loading through the chain would also return
        // the lines Laravel ships (see FrameworkLangPath) and copy them into the app.
        /** @var array<string, mixed> $appTranslations */
        $appTranslations = (new FileLoader($this->filesystem, $defaultLangPath))->load($locale, $groupName);
        /** @var array<string, mixed> $translations */
        $translations = TranslationLines::merge(
            TranslationLines::undot($appTranslations),
            TranslationLines::undot($this->getCustomTranslations($locale, $group)->all()),
        );

        return collect($translations);
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
