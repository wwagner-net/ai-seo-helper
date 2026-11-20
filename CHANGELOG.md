# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-20

### Changed

- **Breaking:** supports TYPO3 14.3 and PHP 8.2 or newer only
- **Breaking:** distributed via Composer only; extension metadata now lives in the Composer manifest
- Backend JavaScript reads its labels from the TYPO3 14 label modules instead of the global label store
- Dependency injection relies on autowiring and attributes; the custom logger and configuration wiring is gone
- Custom language records use the current TCA syntax; the database table is created from TCA
- Own label references use the short translation domain syntax

### Fixed

- German backend labels did not load because of a malformed language attribute in the German label file
- Suggestions for translated news records came back in the default language; the output language and the fetched detail view now follow the language of the news record

### Removed

- Support for TYPO3 11, 12 and 13 (use 0.7.x for those versions)
- Legacy metadata file, legacy table and SQL definition files
- RequireJS modules and all TYPO3 version switches
- Event listener that injected inline labels
- Unused model selection factory
