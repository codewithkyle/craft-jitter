# Just In Time Image Transformations

Jitter is a just in time image transformation plugin for Craft CMS. The API is based on [Imgix](https://docs.imgix.com/apis/url). This plugin was created to be a simple and free alternative to an Imgix style service. It **does not and will not** have all the bells and whistles that other paid services/plugins offer. If you need something a bit more advanced besides basic image transformations I suggest you pay for [Imgix](https://www.imgix.com/pricing) or [Imager X](https://plugins.craftcms.com/imager-x).

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require codewithkyle/jitter

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Jitter.

## Configuring Jitter

Jitter can be configured by adding a `jitter.php` file to your projects `config/` directory.

```php
<?php

return [
    'accessKey' => getenv("S3_PUBLIC_KEY"),
    'secretAccessKey' => getenv("S3_PRIVATE_KEY"),
    'region' => 'us-east-2',
    'bucket' => 'bucket-name',
    'folder' => 'transformed-images',
];
```

## Using Jitter

Requesting an image transformation through the API:

```
/actions/jitter/transform/image?id=1w=768&ar=16:9
```

Requesting an image transformation via Twig:

```twig
{# This will transform the image on page load #}
{% set transformedImageUrl = craft.jitter.transformImage(entry.image[0], { w: 150, ar: 1/1, m: "fit", fm: "gif", q: 10 }) %}

{# For a faster template render build the API URL instead #}
{% set transformedImageUrl = "/actions/jitter/transform/image&id=" ~ entry.image[0].id ~ "&w=150&ar=1:1&m=fit&fm=gif&q=10" %}

<img 
    src="{{ transformedImageUrl }}" 
    srcset="{{ craft.jitter.srcset(entry.image[0], [
        { w: 300, h: 250, },
        { w: 768, ar: 16/9, },
        { w: 1024, ar: 16/9, },
    ]) }}" 
    loading="lazy"
    width="1024"
/>
```

Transformation parameters:

| Parameter     | Default                  | Description                     | Valid options                          |
| ------------- | ------------------------ | ------------------------------- | -------------------------------------- |
| `id`          | `null`                   | the image asset id (required)   | `int`                                  |
| `w`           | base image width         | desired image width             | `int`                                  |
| `h`           | base image height        | desired image height            | `int`                                  |
| `ar`          | base image aspect ratio  | desired aspect ratio            | `int:int`                              |
| `fm`          | `auto`                   | desired image format            | `jpg`, `png`, `gif`, `auto`            |
| `q`           | `80`                     | desired image quality           | `0` to `100`                           |
| `m`           | `clip`                   | how the image should be resized | `crop`, `clip`, `fit`, `letterbox`     |
| `bg`          | `ffffff`                 | letterbox background color      | `hex`                                  |
| `fp-x`        | `0.5`                    | horizontal focus point          | `0` to `1`                             |
| `fp-y`        | `0.5`                    | vertical focus point            | `0` to `1`                             |

The `auto` format type will return a `webp` image when the server can generate the format and the client's browser supports the format.

## Roadmap

- [x] Roadmap & API documentation
- [x] Create image transformation service
- [x] Create image transformation twig variable
- [x] Add AWS S3 bucket support
- [x] Add focus point parameters
- [x] Add `srcset()` functionality
- [x] Add cache clearing functionality
    - [x] Delete local files
    - [x] Delete S3 files
- [ ] Initial release
