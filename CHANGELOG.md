# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [2.3.3] - 2023-03-02

### Fixed

- `checkCache()` null array bug ([#25](https://github.com/codewithkyle/craft-jitter/issues/25))

## [2.3.0] - 2022-08-23

### Added

- CDN support ([#22](https://github.com/codewithkyle/craft-jitter/issues/22))
- automatically delete transformed images when the source image is deleted from Craft ([#19](https://github.com/codewithkyle/craft-jitter/issues/19))
- new config settings:
    - `cdn`
        - must be the CDN's origin URL
    - `acl`
        - controls the files default ACL value
        - supports "private" (default) and "public-read"

### Fixed

- images stored in S3 or Spaces now use the correct `Content-Type` header ([#21](https://github.com/codewithkyle/craft-jitter/issues/21))
    - previously always used `application/octet-stream` (default value for S3-compatible storage solutions)
    - now uses correct MIME type

## [2.2.0] - 2022-07-07

### Added

- support for S3-compatible object storage solutions (like [Digital Ocean Spaces](https://www.digitalocean.com/products/spaces)) ([#16](https://github.com/codewithkyle/craft-jitter/issues/16))
- `craft.jitter.url(asset, params)` method

### Fixed

- improved image caching response times ([#15](https://github.com/codewithkyle/craft-jitter/issues/15))
- `craft.jitter.transformImage()` would cache transformed images using the assets `uid` instead of `id` ([#14](https://github.com/codewithkyle/craft-jitter/issues/14))

### Updated

- composer packages
    - Jitter Core v1.1.0 -> v2.0.0

## [2.1.0] - 2022-05-31

### Added

- `croponly` mode (previously was `crop` mode)

### Fixed

- `clip` mode no longer crops or distorts the image
- `crop` mode inconsistencies -- now resizes before cropping (for old functionality see `croponly` mode)
- focal point out of bounds bug

## [2.0.0] - 2022-05-30

### Added

- Craft 4 support

### Fixed

- Craft 4 & PHP 8 type issues

## [1.2.6] - 2022-05-30

### Fixed

- PHP type errors

### Removed

- Craft 4 support

## [1.2.5] - 2022-05-30

### Fixed

- PHP type errors

## [1.2.4] - 2022-05-30

### Fixed

- Craft version requirements

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

[Unreleased]: https://github.com/codewithkyle/craft-jitter/compare/v2.3.3...HEAD
[2.3.3]: https://github.com/codewithkyle/craft-jitter/compare/v2.3.0...v2.3.3
[2.3.0]: https://github.com/codewithkyle/craft-jitter/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/codewithkyle/craft-jitter/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/codewithkyle/craft-jitter/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.6...v2.0.0
[1.2.6]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.5...v1.2.6
[1.2.5]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.4...v1.2.5
[1.2.4]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.3...v1.2.4
[1.2.3]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/codewithkyle/craft-jitter/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/codewithkyle/craft-jitter/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/codewithkyle/craft-jitter/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/codewithkyle/craft-jitter/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/codewithkyle/craft-jitter/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/codewithkyle/craft-jitter/releases/tag/v1.0.0
