#Laravel Chained Translator

The chained translator can combine several translators that can override each others translations. Typically at some 
point during the development phase, a content manager wants to translate or finetune the translation strings added by
developers. This often results in merge and versioning issues, when developers and content managers are working on
the translation files at the same time.  

The Chained Translator package allows translations created by developers to exist separately from translations edited by 
the content manager in separate `lang` directories. The library merges the translations of both language directories, 
where the translations of the content manager (the custom translations) override those of the developer (the default 
translations).

For instance, the default translations created by developers are written in the default Laravel `lang` directory in
`resources/lang`, and the translations by the content manager are added to `resources/lang-custom`. When a translation 
key exists in the `resources\lang-custom` directory, this is preferred, otherwise we fallback to the default 
translations. 

The package works together with a Laravel Nova tool that provides a UI to let content managers edit translations.

## Installation

Via composer:
```
composer require statikbe/laravel-chained-translator
```

## Configuration

You can publish the configuration by running this command:
```
php artisan vendor:publish --provider="Statikbe\LaravelChainedTranslator\TranslationServiceProvider"
```

### Custom lang directory
By default, the custom translations are saved in `resources/lang-custom`. This can be configured in 
`laravel-chained-translator.php` configuration file with the key: `custom_lang_directory_name`

##TODOs

- support for JSON translation files
- publish service provider
