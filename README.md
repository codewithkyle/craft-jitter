# Just In Time Image Transformations

Jitter is a just in time image transformation plugin for Craft CMS. The API is based on [Imgix](https://docs.imgix.com/apis/url). This plugin was created to be a simple and free alternative to an Imgix style service. It **does not and will not** have all the bells and whistles that other paid services/plugins offer. If you need something a bit more advanced besides basic image transformations I suggest you pay for [Imgix](https://www.imgix.com/pricing) or select a different [Craft Plugin](https://plugins.craftcms.com/categories/assets).

## Requirements

This plugin requires [ImageMagick](https://imagemagick.org/index.php) and the following version of PHP and Craft CMS:

- Craft CMS 3.0.0+ with PHP 7.2+ or 8+
- Craft CMS 4.0.0+ with PHP 8+

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
/jitter/v1/transform?id=1&w=768&ar=16:9
```

Requesting an image transformation via Twig:

```twig
{# This will transform the image on page load #}
{% set transformedImageUrl = craft.jitter.transformImage(entry.image[0], { w: 150, ar: "1:1", m: "fit", fm: "gif", q: 10 }) %}

{# For a faster template render build the API URL instead #}
{% set transformedImageUrl = "/jitter/v1/transform&id=" ~ entry.image[0].id ~ "&w=150&ar=1:1&m=fit&fm=gif&q=10" %}

<img 
    src="{{ transformedImageUrl }}" 
    srcset="{{ craft.jitter.srcset(entry.image[0], [
        { w: 300, h: 250, },
        { w: 768, ar: "16:9", },
        { w: 1024, ar: "16:9", },
    ]) }}" 
    loading="lazy"
    width="1024"
/>
```

Generating transformations via PHP:

```php
$jitter = new \codewithkyle\jitter\services\Transform();
$src = "/jitter/v1/transform?id=" . $image->id . "&w=300&ar=1:1";
$srcset = $jitter->generateSourceSet($image->id, [
    [
        "w" => 300,
        "h" => 250,
    ],
    [
        "w" => 768,
        "ar" => "16:9",
    ],
    [
        "w" => 1024,
        "ar" => "16:9",
    ],
]);
```

Transformation parameters:

| Parameter     | Default                    | Description                     | Valid options                                  |
| ------------- | -------------------------- | ------------------------------- | ---------------------------------------------- |
| `id`          | `null`                     | the image asset id              | `int`                                          |
| `path`        | `null`                     | the image asset id              | `int`                                          |
| `w`           | base image width           | desired image width             | `int`                                          |
| `h`           | base image height          | desired image height            | `int`                                          |
| `ar`          | base image aspect ratio    | desired aspect ratio            | `int`:`int`                                    |
| `fm`          | `auto`                     | desired image format            | `jpg`, `png`, `gif`, `auto`                    |
| `m`           | `clip`                     | how the image should be resized | `crop`, `clip`, `fit`, `letterbox`, `croponly` |
| `q`           | `80`                       | desired image quality           | `0` to `100`                                   |
| `bg`          | `ffffff`                   | letterbox background color      | `hex`                                          |
| `fp-x`        | `0.5` or asset focal point | horizontal focus point          | `0` to `1`                                     |
| `fp-y`        | `0.5` or asset focal point | vertical focus point            | `0` to `1`                                     |

The `auto` format type will return a `webp` image when the server can generate the format and the client's browser supports the format.
