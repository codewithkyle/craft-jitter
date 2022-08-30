# Just In Time Image Transformations

Jitter is a just in time image transformation plugin for Craft CMS. The API is based on [Imgix](https://docs.imgix.com/apis/url). This plugin was created to be a simple and free alternative to an Imgix style service. It **does not and will not** have all the bells and whistles that other paid services/plugins offer. If you need something a bit more advanced besides basic image transformations I suggest you pay for [Imgix](https://www.imgix.com/pricing) or select a different [Craft Plugin](https://plugins.craftcms.com/categories/assets).

## Requirements

This plugin requires [ImageMagick](https://imagemagick.org/index.php) and the following versions of PHP and Craft CMS:

- Craft CMS 4.0.0+ with PHP 8+ (Jitter v2.0+, active)
- Craft CMS 3.0.0+ with PHP 7.2+ (Jitter v1.x, unsupported)

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require codewithkyle/jitter

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Jitter.

## Configuring Jitter

Jitter can be configured to use S3-compatible object storage solutions by adding a `jitter.php` file to your projects `config/` directory. Transformed images will be stored in the storage solution but will still be served from your web server. If you would like to serve images from a CDN read the section below.

```php
<?php

return [
    "accessKey" => getenv("PUBLIC_KEY"),
    "secretAccessKey" => getenv("PRIVATE_KEY"),
    "region" => "region-name",
    "bucket" => "bucket-name",
    "folder" => "transformed-images",
    "endpoint" => getenv("ENDPOINT_URL"),
    "acl" => "private", // supports "private" or "public-read"
];
```

> **Note**: the `endpoint` and `acl` config values are optional. You will only need to use `endpoint` when using an S3-compatible alternative S3 cloud object storage solution (like Digital Ocean Spaces).

## Using a Content Delivery Network (CDN)

Jitter can be configured to use CDN URLs. The `cdn` config value should be the CDN's origin URL. Jitter's `url()` and `srcset()` functions will automatically switch from using the `/jitter/` URL to the CDN URL over time as the image transformations are performed.

```php
<?php

return [
    "accessKey" => getenv("PUBLIC_KEY"),
    "secretAccessKey" => getenv("PRIVATE_KEY"),
    "region" => "region-name",
    "bucket" => "bucket-name",
    "folder" => "transformed-images",
    "endpoint" => getenv("ENDPOINT_URL"),
    "acl" => "public-read",
    "cdn" => "https://demo.cdn.example.com/"
];
```

> **Note**: if you use Craft's template caching or a 3rd party HTML caching service (like Cloudflare's Edge Cache) `/jitter/` image URLs may be cached when a CDN URL is available. We do not recommend disabling your caching systems, however, you may want to consider using a lower TTL to ensure the CDN URLs propagate sooner rather than later.

## Using Jitter

Image transformations via the API:

```
/jitter/v1/transform?id=1&w=768&ar=16:9
```

Image transformations via Twig:

```twig
{# This will transform the image when the template renders. #}
{# This can cause site-wide performance issues depending on how many times this method is used (per template) and how much RAM is available. #}
{% set transformedImageUrl = craft.jitter.transformImage(entry.image[0], { w: 150, ar: "1:1", m: "fit", fm: "gif", q: 10 }) %}

{# For a faster template render build the API URL instead #}
{# If you have configured Jitter to use CDN URLs this value will swap to the CDN URL after the image has been transformed #}
{% set transformedImageUrl = craft.jitter.url(entry.image[0], { w: 150, ar: "1:1", m: "fit", fm: "gif", q: 10 }) %}

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

Image transformations via PHP:

```php
$jitter = new \codewithkyle\jitter\services\Transform();
$src = $jitter->generateURL([
    "id" => $image->id,
    "w" => 300,
    "ar" => "1:1",
]);
$srcset = $jitter->generateSourceSet($image, [
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

## Transformation parameters

| Parameter     | Default                    | Description                     | Valid options                                  |
| ------------- | -------------------------- | ------------------------------- | ---------------------------------------------- |
| `id`          | `null`                     | the image asset id              | `int`                                          |
| `path`        | `null`                     | the image file path             | `string`                                       |
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
