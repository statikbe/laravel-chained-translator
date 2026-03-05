<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

class ChainedTranslatorConfig
{
    const PACKAGE = 'laravel-chained-translator';

    /**
     * The directory name of the translations that will override the default translations.
     */
    public function getCustomLangDirectoryName(): string
    {
        return (string) config(self::PACKAGE . '.custom_lang_directory_name', 'lang-custom');
    }

    /**
     * Whether a .gitignore file should be added to the custom language directory.
     */
    public function shouldAddGitignoreToCustomLangDirectory(): bool
    {
        return config(self::PACKAGE . '.add_gitignore_to_custom_lang_directory', true) === true;
    }

    /**
     * Whether translation keys should be saved as nested arrays (true) or dotted keys (false).
     */
    public function shouldGroupKeysInArray(): bool
    {
        return config(self::PACKAGE . '.group_keys_in_array', true) !== false;
    }

    /**
     * The group name used for all JSON translations.
     */
    public function getJsonGroupName(): string
    {
        return (string) config(self::PACKAGE . '.json_group', 'single');
    }
}
