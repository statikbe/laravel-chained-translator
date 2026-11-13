# Changelog

All notable changes to the __Laravel Chained Translator__ will be documented in this file.

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
