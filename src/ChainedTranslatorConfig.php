<?php

declare(strict_types=1);

namespace Statikbe\LaravelChainedTranslator;

use Illuminate\Support\Facades\Config;

class ChainedTranslatorConfig
{
    /**
     * The directory name of the translations that will override the default translations.
     */
    public function getCustomLangDirectoryName(): string
    {
        return Config::string(BaseTranslationServiceProvider::NAME . '.custom_lang_directory_name', 'lang-custom');
    }

    /**
     * Whether a .gitignore file should be added to the custom language directory.
     */
    public function shouldAddGitignoreToCustomLangDirectory(): bool
    {
        return Config::boolean(BaseTranslationServiceProvider::NAME . '.add_gitignore_to_custom_lang_directory', true);
    }

    /**
     * Whether translation keys should be saved as nested arrays (true) or dotted keys (false).
     */
    public function shouldGroupKeysInArray(): bool
    {
        return Config::boolean(BaseTranslationServiceProvider::NAME . '.group_keys_in_array', true);
    }

    /**
     * The group name used for all JSON translations.
     */
    public function getJsonGroupName(): string
    {
        return Config::string(BaseTranslationServiceProvider::NAME . '.json_group', 'single');
    }
}
