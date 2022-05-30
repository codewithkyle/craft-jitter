# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [1.2.3] - 2022-05-30

### Fixed

- PHP 8 bugs introduced by Craft's Rector library

## [1.2.2] - 2022-05-21

### Added

- PHP 8 support

## [1.2.1] - 2021-06-19

### Fixed

- `craft.jitter.transformImage()` bug ([#9](https://github.com/codewithkyle/craft-jitter/issues/9))
- S3 bucket config bug
- `craft.jitter.srcset()` removes the temp files it creates

## [1.2.0] - 2021-06-12

### Fixed

- fixed S3 ACL issues ([#6](https://github.com/codewithkyle/craft-jitter/issues/6))

### Added

- switched to Jitter Core ([#5](https://github.com/codewithkyle/craft-jitter/issues/5))

## [1.1.2] - 2021-06-06

### Fixed

- invalid S3 asset paths after clearing the runtime cache ([#7](https://github.com/codewithkyle/craft-jitter/issues/7))

## [1.1.1] - 2020-11-19

### Fixed

- changed `$asset->url` to `$asset->getImageTransformSourcePath()` ([#3](https://github.com/codewithkyle/craft-jitter/issues/3))
- changed file extension regex pattern from `/(\..*)$/` to `/(\..{1,4})$/` ([#3](https://github.com/codewithkyle/craft-jitter/issues/3))

## [1.1.0] - 2020-10-26

### Added

- native focus point fallback [#2](https://github.com/codewithkyle/craft-jitter/issues/2)

## [1.0.0] - 2020-09-16

### Added

- basic documentation
- create image transformation service
- create image transformation twig variable
- AWS S3 bucket support
- focus point parameters
- `srcset()` functionality
- cache clearing functionality
    - delete local files
    - delete S3 files

[Unreleased]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.3...HEAD
[1.2.3]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/codewithkyle/craft-jitter/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/codewithkyle/craft-jitter/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/codewithkyle/craft-jitter/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/codewithkyle/craft-jitter/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/codewithkyle/craft-jitter/releases/tag/v1.0.0
