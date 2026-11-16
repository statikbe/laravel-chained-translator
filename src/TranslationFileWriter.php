<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Brick\VarExporter\ExportException;
use Brick\VarExporter\VarExporter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Statikbe\LaravelChainedTranslator\Exceptions\InvalidTranslationPathException;
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
     * Reads the translations of a group. A file that cannot be parsed is reported instead of read as empty,
     * because saving a translation writes the whole file back and would drop all its other translations.
     *
     * @return Collection<string, mixed>
     * @throws SaveTranslationFileException
     */
    public function readGroupTranslations(string $locale, string $group): Collection
    {
        $groupPath = $this->getGroupPath($locale, $group);

        if (!$this->files->exists($groupPath)) {
            /** @var Collection<string, mixed> */
            return collect([]);
        }

        $contents = $this->nameParser->isJsonGroup($group) ? $this->files->get($groupPath) : null;
        // Invalid JSON decodes to null. An empty JSON file has no translations.
        // @mago-expect analysis:mixed-assignment
        $translations = match (true) {
            $contents === null => $this->files->getRequire($groupPath),
            trim($contents) === '' => [],
            default => json_decode($contents, true),
        };

        if (!is_array($translations)) {
            throw new SaveTranslationFileException(
                "The translation file {$groupPath} does not contain a list of translations. Fix or remove it first.",
            );
        }

        /** @var Collection<string, mixed> */
        return collect($translations);
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
        // Only writing creates directories, reading a group that does not exist must not.
        $this->ensureDirectoryExists(dirname($groupPath));
        $translationsArray = $translations->toArray();

        $contents = $this->nameParser->isJsonGroup($group)
            ? $this->encodeJsonTranslations($translationsArray)
            : $this->encodePhpTranslations($translationsArray);

        // Written to a temporary file that then replaces the file, so a request loading the translations
        // meanwhile reads the old or the new file, never half a file.
        $tempPath = $groupPath . '.' . bin2hex(random_bytes(8)) . '.tmp';

        if ($this->files->put($tempPath, $contents) === false || !$this->files->move($tempPath, $groupPath)) {
            $this->files->delete($tempPath);

            throw new SaveTranslationFileException("The translation file {$groupPath} could not be saved.");
        }

        // Clear the opcache of the group file, because otherwise in the next request an old cached
        // file can be read in and the saved translation can be overwritten.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($groupPath, true);
        }
    }

    public function getConfig(): ChainedTranslatorConfig
    {
        return $this->config;
    }

    /**
     * @throws InvalidTranslationPathException
     */
    public function localeFolderExists(string $locale): bool
    {
        $this->nameParser->assertValidLocale($locale);

        return $this->files->exists($this->basePath . DIRECTORY_SEPARATOR . $locale);
    }

    /**
     * @throws InvalidTranslationPathException
     */
    public function createLocaleFolder(string $locale): bool
    {
        $this->nameParser->assertValidLocale($locale);

        return $this->files->makeDirectory($this->basePath . DIRECTORY_SEPARATOR . $locale, 0o755, true);
    }

    /**
     * Returns the file path of the translation group. The locale and group are validated before any
     * path is built or directory is created, so the path cannot point outside the language directory.
     *
     * @throws InvalidTranslationPathException
     */
    public function getGroupPath(string $locale, string $group, ?string $languagePath = null): string
    {
        $this->nameParser->assertValidLocale($locale);
        $this->nameParser->assertValidGroup($group);

        if ($this->nameParser->isJsonGroup($group)) {
            return ($languagePath ?? $this->basePath) . DIRECTORY_SEPARATOR . $locale . '.json';
        }

        [$basePath, $groupName] = $this->getGroupBasePath($locale, $group, $languagePath);

        return $basePath . DIRECTORY_SEPARATOR . $groupName . '.php';
    }

    /**
     * @return array{0: string, 1: string} [basePath, groupName]
     */
    private function getGroupBasePath(string $locale, string $group, ?string $languagePath = null): array
    {
        $languagePath ??= $this->basePath;

        [$namespace, $group] = $this->nameParser->extractNamespace($group);
        if ($namespace !== null) {
            $namespace = 'vendor' . DIRECTORY_SEPARATOR . $namespace;
        }

        [$subFolders, $group] = $this->nameParser->extractSubfolders($group);
        $subFolders = $subFolders !== null ? str_replace('/', DIRECTORY_SEPARATOR, $subFolders) : null;

        $groupBasePath = implode(DIRECTORY_SEPARATOR, array_filter([$languagePath, $namespace, $locale, $subFolders]));

        return [$groupBasePath, $group];
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
            $translations = ct_array_undot($translations);
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
