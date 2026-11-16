<p align="center"><img src="documentation/img/banner-laravel-chained-translator.png" alt="Laravel Chained Translator"></p>

# Laravel Chained Translator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/statikbe/laravel-chained-translator.svg?style=flat-square)](https://packagist.org/packages/statikbe/laravel-chained-translator)
[![Total Downloads](https://img.shields.io/packagist/dt/statikbe/laravel-chained-translator.svg?style=flat-square)](https://packagist.org/packages/statikbe/laravel-chained-translator)

The chained translator can combine several translators that can override each other's translations. Typically, at some 
point during the development phase, a content manager wants to translate or finetune the translation strings added by
developers. This often results in merge and versioning issues, when developers and content managers are working on
the translation files at the same time.  

The Chained Translator package allows translations created by developers to exist separately from translations edited by 
the content manager in separate `lang` directories. The library merges the translations of both language directories, 
where the translations of the content manager (the custom translations) override those of the developer (the default 
translations).

For instance, the default translations created by developers are written in the default Laravel `lang` directory, and
the translations by the content manager are added to `lang-custom`. When a translation key exists in the `lang-custom`
directory, this is preferred, otherwise we fall back to the default translations.

The default translations also include the translations Laravel ships itself (validation, authentication, pagination
and password reset messages), just like Laravel's own translator. Since Laravel 11 these are not in your app's `lang`
directory unless you publish them, but the content manager can still edit them.

We offer two packages that provide a UI to let content managers edit translations.
- [Laravel Nova Chained Translation Manager](https://github.com/statikbe/laravel-nova-chained-translation-manager) for Laravel Nova
- [Laravel Filament Chained Translation Manager](https://github.com/statikbe/laravel-filament-chained-translation-manager) for Laravel Filament

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13

## Installation

Via composer:
```
composer require statikbe/laravel-chained-translator
```

## Commands

### Merge the custom translations back into the default translation files
If you want to combine the translation files made in the current environment by the content manager with the default 
translation files, you can use the following command. You need to pass the locale as a parameter, since this library is
agnostic of the locales supported by your Laravel application. Laravel sadly does not have a default supported locales 
list. So if you want to merge all files for all supported locales, run this command for each locale.

For example, for French: 

```shell script
php artisan chainedtranslator:merge fr
``` 

This command can be useful to merge the translation work of a translator back into the default translation files.

What the command writes to the default `lang` directory:

- **Your app's translation files** (e.g. `lang/fr/validation.php`) get your app's own lines plus the custom lines. The
  lines Laravel ships itself are not copied into them: a file that only overrides a few lines stays that way.
- **Package translation files** (in `lang/vendor/`) get all lines of the package, your app's overrides and the custom
  lines.
- **JSON translations** (`lang/fr.json`) get your app's lines plus the custom lines.
- PHP translation files are written as nested arrays, whatever `group_keys_in_array` is set to. A custom line
  `'between.numeric'` replaces `'numeric'` inside `'between'`.
- Groups that only exist in the custom lang directory are merged too, and a missing `lang` directory is created.

The custom translations stay in the custom lang directory, so they keep overriding the default translations until you
remove them. The command exits with code 0 when the merge succeeded, and with 1 when it failed.

## Configuration

You can publish the configuration by running this command:
```bash
php artisan vendor:publish --provider="Statikbe\LaravelChainedTranslator\TranslationServiceProvider" --tag=config
```

### The following configuration fields are available:

#### 1. Custom lang directory
By default, the custom translations are saved in `lang-custom` in the root of your project, next to `lang`. Apps that
still have their translations in `resources/lang` get `resources/lang-custom`. The directory name can be configured
using `custom_lang_directory_name`. The directory is created when it does not exist. It also holds a `.lock` file,
which keeps translations saved at the same time from overwriting each other.

#### 2. .gitignore in custom lang directory
If `add_gitignore_to_custom_lang_directory` is set to true (the default), a .gitignore file that ignores all custom 
translations is added when the custom language directory is created.

#### 3. Group keys in nested arrays
If `group_keys_in_array` is set to true, dotted translation keys will be mapped into arrays in the custom translation
files. This setting does not affect the merge command, which always writes nested arrays to the default `lang` directory.

Set to __true__: saved as nested arrays, f.e.
```php
'key' => [
   'detail' => 'translation',
]
```

Set to __false__: saved as dotted keys, f.e.

```php
'key.detail' => 'translation',
```

#### 4. Custom json group name
JSON translations are shown and saved as a single translation group, named `json-file` by default. You can change this
group name with `json_group`.

## Allowed locale and group names

Locales and translation groups are used to build file paths, so they are validated before any translation file is read or written:

- **Locales** may only contain letters, digits, `_` and `-` (e.g. `en`, `en_US`, `pt-BR`, `es-419`).
- **Groups** may contain a namespace (`package::`) and subfolders (`admin/messages`). Every namespace, folder and file name may only contain letters, digits, `_`, `-` and `.`, and must not start with a `.`.

An invalid locale or group throws a `Statikbe\LaravelChainedTranslator\Exceptions\InvalidTranslationPathException`.

## Custom translation files that cannot be read

Saving a translation rewrites the whole custom translation file. When a custom translation file cannot be read, e.g. a
JSON file with a syntax error, saving a translation to it throws a
`Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException` and leaves the file unchanged. Fix or
remove the file to save translations to it again.

## Credits

We used [Joe Dixon's](https://github.com/joedixon) translation libraries as a source of technical expertise and inspiration:
- [Laravel Translation](https://github.com/joedixon/laravel-translation)

Thanks a lot for the great work!

## License
The MIT License (MIT). Please see [license file](LICENSE.md) for more information.
