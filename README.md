# Laravel Chained Translator

The chained translator can combine several translators that can override each others translations. Typically, at some 
point during the development phase, a content manager wants to translate or finetune the translation strings added by
developers. This often results in merge and versioning issues, when developers and content managers are working on
the translation files at the same time.  

The Chained Translator package allows translations created by developers to exist separately from translations edited by 
the content manager in separate `lang` directories. The library merges the translations of both language directories, 
where the translations of the content manager (the custom translations) override those of the developer (the default 
translations).

For instance, the default translations created by developers are written in the default Laravel `lang` directory in
`resources/lang`, and the translations by the content manager are added to `resources/lang-custom`. When a translation 
key exists in the `resources/lang-custom` directory, this is preferred, otherwise we fallback to the default 
translations. 

The package works together with our [Laravel Nova Chained Translation Manager](https://github.com/statikbe/laravel-nova-chained-translation-manager) that provides a UI to let content managers edit translations.

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

## Configuration

You can publish the configuration by running this command:
```
php artisan vendor:publish --provider="Statikbe\LaravelChainedTranslator\TranslationServiceProvider"
```

The following configuration fields are available:

- __Custom lang directory__:
By default, the custom translations are saved in `resources/lang-custom`. This can be configured in 
`laravel-chained-translator.php` configuration file with the key: `custom_lang_directory_name`

- __Add .gitignore to custom lang directory__: 
If the config key `add_gitignore_to_custom_lang_directory` is set to true, a .gitignore file is added to the custom 
language directory.
 
## TODO's & Ideas

- support for JSON translation files
- option to overwrite the default `lang` directory. This could be useful on local and staging environments to manage the
developer translations. 

## Credits

We used [Joe Dixon's](https://github.com/joedixon) translation libraries as a source of technical expertise and inspiration:
- [Laravel Translation](https://github.com/joedixon/laravel-translation)

Thanks a lot for the great work!

## License
The MIT License (MIT). Please see [license file](LICENSE.md) for more information.
