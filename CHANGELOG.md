# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Fixed

- fixed S3 ACL issues ([#6](https://github.com/codewithkyle/craft-jitter/issues/6))

### Added

- switched to Jitter Core ([#5](https://github.com/codewithkyle/craft-jitter/issues/5))

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

[Unreleased]: https://github.com/codewithkyle/craft-jitter/compare/v1.1.1...HEAD
[1.1.1]: https://github.com/codewithkyle/craft-jitter/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/codewithkyle/craft-jitter/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/codewithkyle/craft-jitter/releases/tag/v1.0.0