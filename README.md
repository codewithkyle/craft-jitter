# Just In Time Image Transformations

JITIT is a just in time image transformation plugin for Craft CMS. The API is based on [Imgix](https://docs.imgix.com/apis/url). This plugin was created to be a simple and free alternative to an Imgix style service. It **does not and will not** have all the bells and whisles that other paid services/plugins offer. If you need something a bit more advanced besides basic image transformations I suggest you pay for [Imgix](https://www.imgix.com/pricing) or [Imager X](https://plugins.craftcms.com/imager-x).

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require codewithkyle/jitit

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for JITIT.

## Configuring JITIT

JITIT can be configured by adding a `jitit.php` file to your projects `config/` directory.

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

## Using JITIT

Requesting an image transformation through the API:

```
/actions/jitit/transform/image?id=1w=768&ar=16:9
```

API response:

```typescript
interface {
    success: boolean;
    error: string;
    url: string;
}
```

Requesting an image transformation via Twig:

```twig
{% set transformedImageUrl = craft.jitit.transformImage(entry.image[0], { w: 150, ar: 1/1, m: "resize", fm: "gif", q: 10 }) %}
<img 
    src="{{ transformedImageUrl }}" 
    srcset="{{ craft.jitit.srcset(entry.image[0], [
        { w: 300, h: 250, },
        { w: 768, ar: 16/9, },
        { w: 1024, ar: 16/9, },
    ]) }}" 
/>
```

Image transformations require the follow parameters:

1. `id` - the asset id

Optional transformation parameters:

| Parameter     | Default                  | Description                     | Valid options                       |
| ------------- | ------------------------ | ------------------------------- | ----------------------------------- |
| `w`           | base image width         | desired image width             | `int`                               |
| `h`           | base image height        | desired image height            | `int`                               |
| `ar`          | base image aspect ratio  | desired aspect ratio            | `int:int`                           |
| `fm`          | `auto`                   | desired image format            | `jpg`, `png`, `gif`, `auto`         |
| `q`           | `80`                     | desired image quality           | `0` to `100`                        |
| `m`           | `crop`                   | how the image should be resized | `crop`, `fit`, `letterbox`          |
| `bg`          | `ffffff`                 | letterbox background color      | `hex`                               |

## JITIT Roadmap

- [x] Roadmap & API documentation
- [x] Create image transformation service
- [x] Create image transformation twig variable
- [x] Add AWS S3 bucket support
- [ ] Add focus point parameters
- [ ] Add `srcset()` functionality
- [ ] Add GD support
- [ ] Add cache clearing functionality
- [ ] Initial release
