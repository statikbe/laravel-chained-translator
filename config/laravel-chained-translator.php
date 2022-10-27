<?php

return [
    /*
    |--------------------------------------------------------------------------
    | The name of the lang directory containing the custom translations
    |--------------------------------------------------------------------------
    | The directory name of the translations of that will override the default
    | translations.
    */
    'custom_lang_directory_name' => 'lang-custom',

    /*
    |--------------------------------------------------------------------------
    | Adds a .gitignore file to the custom language directory
    |--------------------------------------------------------------------------
    | If true, a .gitignore file is added to the custom language directory, if
    | the directory does not exist yet.
    */
    'add_gitignore_to_custom_lang_directory' => true,

    /*
    |--------------------------------------------------------------------------
    | Group translation keys in to arrays
    |--------------------------------------------------------------------------
    | You can choose if the translations keys are in dotted notation or grouped
    | using arrays.
    |
    | True: saved as nested arrays, f.e.
    |   'key' => [
    |       'detail' => 'translation',
    |   ]
    |
    | False: saved as dotted keys, f.e.
    |   'key.detail' => 'translation',
    |
    */
    'group_keys_in_array' => false,

    /*
    |--------------------------------------------------------------------------
    | Json group name
    |--------------------------------------------------------------------------
    | You can customize what group is used for all json translations.
    */
    'json_group' => 'json-file',
];
