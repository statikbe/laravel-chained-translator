<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Discovers all translation groups available in the application's lang directory.
 */
class TranslationGroupFinder
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly TranslationGroupNameParser $nameParser,
    ) {}

    /**
     * Returns a deduplicated list of all translation group identifiers.
     *
     * @return array<int, string>
     */
    public function findAll(): array
    {
        $groups = [];
        $langDirPath = function_exists('lang_path') ? lang_path() : resource_path('lang');
        $filesAndDirs = $this->files->allFiles($langDirPath);

        foreach ($filesAndDirs as $file) {
            /* @var SplFileInfo $file */
            $group = $this->resolveGroupFromFile($file);

            if ($group !== null) {
                $groups[$group] = $group;
            }
        }

        return array_values($groups);
    }

    /**
     * Determines the translation group identifier for a single file, or null if the file
     * is a directory or an unrecognised file type.
     */
    private function resolveGroupFromFile(SplFileInfo $file): ?string
    {
        if ($file->isDir()) {
            return null;
        }

        $relativePath = $file->getRelativePath();
        $vendorPath = strstr($relativePath, 'vendor');

        if ($vendorPath !== false) {
            return $this->resolveVendorGroup($file, $vendorPath);
        }

        return $this->resolveRegularGroup($file, $relativePath);
    }

    private function resolveVendorGroup(SplFileInfo $file, string $vendorPath): ?string
    {
        $vendorPath = Str::replaceFirst('vendor' . DIRECTORY_SEPARATOR, '', $vendorPath);
        $subFolders = null;
        $namespace = null;

        if (self::isPhpFile($file->getRelativePathname())) {
            $options = explode(DIRECTORY_SEPARATOR, $vendorPath);
            $namespace = $options[0];
            unset($options[0], $options[1]);
            $subFolders = implode(DIRECTORY_SEPARATOR, array_filter($options));
        }

        if (self::isJsonFile($file->getRelativePathname())) {
            return $this->nameParser->getJsonGroupName();
        }

        if (!self::isPhpFile($file->getRelativePathname())) {
            return null;
        }

        $prefix = ($namespace ?? '') . '::';

        return $prefix
        . implode(DIRECTORY_SEPARATOR, array_filter([$subFolders, $file->getFilenameWithoutExtension()]));
    }

    private function resolveRegularGroup(SplFileInfo $file, string $relativePath): ?string
    {
        if (self::isJsonFile($file->getRelativePathname())) {
            return $this->nameParser->getJsonGroupName();
        }

        if (!self::isPhpFile($file->getRelativePathname())) {
            return null;
        }

        $options = explode(DIRECTORY_SEPARATOR, $relativePath);
        unset($options[0]);
        $subFolders = implode(DIRECTORY_SEPARATOR, array_filter($options));

        return implode(DIRECTORY_SEPARATOR, array_filter([$subFolders, $file->getFilenameWithoutExtension()]));
    }

    /**
     * Check if a file has a PHP extension.
     */
    private static function isPhpFile(string $filename): bool
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'php';
    }

    /**
     * Check if a file has a JSON extension.
     */
    private static function isJsonFile(string $filename): bool
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'json';
    }
}
