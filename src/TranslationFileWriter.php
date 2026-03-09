<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Brick\VarExporter\ExportException;
use Brick\VarExporter\VarExporter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException;

/**
 * Handles reading and writing translation files to disk.
 */
class TranslationFileWriter
{
    private readonly ChainedTranslatorConfig $config;

    public function __construct(
        private readonly Filesystem $files,
        private readonly TranslationGroupNameParser $nameParser,
        private readonly string $basePath,
        ?ChainedTranslatorConfig $config = null,
    ) {
        $this->config = $config ?? new ChainedTranslatorConfig();
    }

    /**
     * @return Collection<string, mixed>
     */
    public function readGroupTranslations(string $locale, string $group): Collection
    {
        $groupPath = $this->getGroupPath($locale, $group);

        if (!$this->files->exists($groupPath)) {
            /** @var Collection<string, mixed> */
            return collect([]);
        }

        if ($this->nameParser->isJsonGroup($group)) {
            $raw = file_get_contents($groupPath);
            /** @var array<string, mixed> $jsonData */
            $jsonData = json_decode($raw !== false ? $raw : '{}', true) ?? [];

            return collect($jsonData);
        }

        /** @var array<string, mixed> $fileData */
        $fileData = $this->files->getRequire($groupPath);

        return collect($fileData);
    }

    /**
     * @param Collection<string, mixed> $translations
     * @throws SaveTranslationFileException
     */
    public function writeGroupTranslations(
        string $locale,
        string $group,
        Collection $translations,
        ?string $languagePath = null,
    ): void {
        $groupPath = $this->getGroupPath($locale, $group, $languagePath);
        $translationsArray = $translations->toArray();

        $contents = $this->nameParser->isJsonGroup($group)
            ? $this->encodeJsonTranslations($translationsArray)
            : $this->encodePhpTranslations($translationsArray);

        $success = $this->files->put($groupPath, $contents);

        if ($success === false) {
            throw new SaveTranslationFileException("The translation file {$groupPath} could not be saved.");
        }

        // Clear the opcache of the group file, because otherwise in the next request an old cached
        // file can be read in and the saved translation can be overwritten.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($groupPath, true);
        }
    }

    public function localeFolderExists(string $locale): bool
    {
        return $this->files->exists($this->basePath . DIRECTORY_SEPARATOR . $locale);
    }

    public function createLocaleFolder(string $locale): bool
    {
        return $this->files->makeDirectory($this->basePath . DIRECTORY_SEPARATOR . $locale, 0o755, true);
    }

    public function getGroupPath(string $locale, string $group, ?string $languagePath = null): string
    {
        if ($this->nameParser->isJsonGroup($group)) {
            return ($languagePath ?? $this->basePath) . DIRECTORY_SEPARATOR . $locale . '.json';
        }

        $basePath = $this->getGroupBasePath($locale, $group, $languagePath);

        $this->nameParser->pullNamespace($group);
        $this->nameParser->pullSubfolders($group);

        return $basePath . DIRECTORY_SEPARATOR . $group . '.php';
    }

    private function getGroupBasePath(string $locale, string $group, ?string $languagePath = null): string
    {
        $languagePath ??= $this->basePath;

        $namespace = $this->nameParser->pullNamespace($group);
        if ($namespace !== null) {
            $namespace = 'vendor' . DIRECTORY_SEPARATOR . $namespace;
        }

        $subFolders = $this->nameParser->pullSubfolders($group);

        $groupBasePath = implode(DIRECTORY_SEPARATOR, array_filter([$languagePath, $namespace, $locale, $subFolders]));

        $this->ensureDirectoryExists($groupBasePath);

        return $groupBasePath;
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->exists($path)) {
            $this->files->makeDirectory($path, 0o755, true);
        }
    }

    /**
     * @param array<string, mixed> $translations
     * @throws SaveTranslationFileException
     */
    private function encodePhpTranslations(array $translations): string
    {
        if ($this->config->shouldGroupKeysInArray()) {
            $translations = array_undot($translations);
        }

        ksort($translations);

        try {
            return "<?php\n\nreturn " . VarExporter::export($translations) . ';' . \PHP_EOL;
        } catch (ExportException $ex) {
            throw new SaveTranslationFileException(
                'The translations could not be transformed to the .php translation file.',
                0,
                $ex,
            );
        }
    }

    /**
     * @param array<string, mixed> $translations
     * @throws SaveTranslationFileException
     */
    private function encodeJsonTranslations(array $translations): string
    {
        ksort($translations);

        $encoded = json_encode($translations, JSON_PRETTY_PRINT);

        if ($encoded === false) {
            throw new SaveTranslationFileException(
                'The translations could not be encoded to JSON: ' . json_last_error_msg(),
            );
        }

        return $encoded;
    }
}
