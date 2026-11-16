# Changelog

All notable changes to the __Laravel Chained Translator__ will be documented in this file.

## Version v3.0.0
### Fixed
- On Laravel 11+, the default loader now also reads the translations Laravel ships in `Illuminate/Translation/lang`, as Laravel's own loader does. Before, validation and pagination messages showed as bare keys (e.g. `validation.required`) unless the app's `lang/` directory had complete copies of the framework files.
- The translation groups Laravel ships (`validation`, `auth`, `pagination`, `passwords`) are now listed for editing, also when the app's `lang/` directory has no file for them. Edits to these groups are now merged by `chainedtranslator:merge`; before, they stayed in the custom lang directory.
- Listing translation groups and `chainedtranslator:merge` no longer fail with a `DirectoryNotFoundException` when the app has no `lang/` directory, the default since Laravel 11.
- `chainedtranslator:merge` also merges groups that only exist in the custom lang directory, e.g. JSON translations in an app without `lang/en.json`. Before, these were skipped.
- `chainedtranslator:merge` now exits with code 0 on success and 1 on failure. Before, the exit codes were reversed.
- `ChainLoader` now supports `addPath()`. Before, `Translator::addPath()` and a package calling `loadTranslationsFrom()` without a namespace failed with "Call to undefined method".
- Translations that packages add with `addJsonPath()` no longer override the app's own JSON translations in `lang/`. The custom loader now ignores paths that packages add.
- Saving a translation no longer wipes a custom translation file that cannot be parsed, e.g. JSON with a trailing comma. Reading such a file now throws a `SaveTranslationFileException` and leaves it unchanged.
- A folder whose name contains "vendor", e.g. `lang/en/vendors/`, is no longer mistaken for package translations. Only `lang/vendor/` holds package translations.
- PHP files directly in `lang/`, outside a locale folder, are no longer listed as translation groups. Laravel does not load them.
- Reading translations no longer creates empty folders in the custom lang directory, and `chainedtranslator:merge` no longer creates an empty locale folder there.
- `chainedtranslator:merge` with an invalid locale now shows the error and exits with code 1, instead of a stack trace.
- Saving translations at the same time no longer loses one of them. A save now waits for another save to finish, by locking `.lock` in the custom lang directory, and then reads the file with the other translation in it.
- Translation files are now written to a temporary file that replaces the file, so a request that loads translations at the same time no longer reads a half-written file.
- Custom translations now also apply when a group is read as an array, without merging first. Before, an edited validation attribute name (`validation.attributes.email`) or custom validation message (`validation.custom.*`) was ignored by the validator until `chainedtranslator:merge` ran, as the validator reads these lines as arrays.
- An edit of a line under a key with dots, e.g. the custom validation message `'custom' => ['items.*.name' => ['required' => ...]]`, is placed in that key, when loading and when merging. Before, merging added a second, nested copy (`'items' => ['*' => ['name' => ...]]`).
### Changed
- `ChainLoader::load()` places dotted keys inside the nested arrays of the lines (see `TranslationLines`). A group with a flat key such as `'menu.home'` now also returns `['home' => ...]` for `__('group.menu')`, where it returned the key before. Lookups of single lines are unchanged. JSON translations are not changed.
- `chainedtranslator:merge` writes nested arrays to `lang/` for PHP groups, also for package groups and whatever `group_keys_in_array` is set to. A custom line `'between.numeric'` now replaces `'numeric'` inside `'between'`, instead of being added as a second, dotted key that hides later edits of the nested line. Dotted keys from earlier merges are folded into the nested arrays too. `group_keys_in_array` now only controls how the custom lang directory stores keys. JSON keys are not changed.
- `ct_array_undot()` merges dotted keys into nested arrays whatever their order, and keeps a dotted key as it is when nesting would replace a translation line, e.g. `'between.numeric'` when `'between'` is a string, or when it has an empty segment, e.g. `'etc.'`.
- Requires Laravel 11+ and PHP 8.2+. Versions 2.6.0 and 2.7.0 already failed on Laravel 9 and 10, because they use the typed config getters that Laravel added in version 11.
- `chainedtranslator:merge` writes only the app's own lines plus the custom lines to plain (non-namespaced) groups, never the lines Laravel ships. An app's override-only file such as `lang/en/validation.php` stays override-only. Namespaced (package) groups and JSON translations are merged as before.

## Version 2.7.0
### Security
- Locales and translation group names are now validated before they are used to build translation file paths. Upgrading is strongly recommended. See [GHSA-jg57-w2jq-7jmj](https://github.com/statikbe/laravel-chained-translator/security/advisories/GHSA-jg57-w2jq-7jmj).
### Changed
- An invalid locale or translation group now throws `Statikbe\LaravelChainedTranslator\Exceptions\InvalidTranslationPathException` (an `InvalidArgumentException`) when saving, reading or loading translations. See [Allowed locale and group names](README.md#allowed-locale-and-group-names).
### Added
- `ChainLoader` exposes the aggregated translation paths of its loaders via `paths()`, e.g. for `barryvdh/laravel-ide-helper`.

## Version 2.2.0
### Added
- Added support for Laravel 10
- Added return types
## Version 2.1.0
### Fixed
- Editing translations in nested folders
### Added
- Support for json translations
- Config option 'group_keys_in_array'
- Config option 'json_group'

## Version 2.0.0
### Updated
- Added support for Laravel 9 and the moved lang dir

## Version 1.3.2
### Fixed
- Merge (custom-lang into lang) command not deleting translation keys anymore.
### Added
- Gitignore now generates with content

## Version 1.2.0
### Fixed
- Nova does not load all dependencies on Nova tool routes. This fixes the dependencies so the injection can be deferred.

## Version 1.1.0
### Added
- command to merge custom translations into the default translation files.

## Version 1.0.0 
### Added
Initial version
