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
        'imageUrl' => 'http://s3-us-east-2.amazonaws.com/transformed-images/',
        'accessKey' => getenv("S3_PUBLIC_KEY"),
        'secretAccessKey' => getenv("S3_PRIVATE_KEY"),
        'region' => 'us-east-2',
        'bucket' => 'bucket-name',
        'folder' => 'transformed-images',
        'requestHeaders' => array(),
        'storageType' => 'standard',
];
```

## Using JITIT

Requesting an image transformation via API:

```
/jiti/transform/image?id=1w=768&ar=16:9
```

Requesting an image transformation via Twig:

```twig
{% set image = craft.jitit.transformImage(element.image[0], { width: 768, ratio: 16/9 }) %}
<img src="{{ image.url }}" />
```

Image transformations require the follow parameters:

1. `id` - the asset id

Optional transformation parameters:

| Parameter     | Default                  | Description               | Valid options                       |
| ------------- |:------------------------:|:-------------------------:|------------------------------------:|
| `w`           | base image width         | desired image width       | `number`                            |
| `h`           | base image height        | desired image height      | `number`                            |
| `ar`          | base image aspect ratio  | desired aspect ratio      | `number:number`                     |
| `fm`          | `auto`                   | desired image format      | `jpg`, `png`, `webp`, `gif`, `auto` |
| `q`           | `80`                     | desired image quality     | `0` to `100`                        |

## JITIT Roadmap

- [x] Roadmap & API documentation
- [ ] Create image transformation service
- [ ] Add AWS S3 bucket support
- [ ] Initial release
