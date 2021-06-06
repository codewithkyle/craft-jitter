<?php
/**
 * Jitter plugin for Craft CMS 3.x
 *
 * A just in time image transformation service.
 *
 * @link      https://kyleandrews.dev/
 * @copyright Copyright (c) 2020 Kyle Andrews
 */

namespace codewithkyle\jitter\services;

use Yii;
use Craft;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use craft\base\Component;
use craft\elements\Asset;
use Imagine\Imagick\Imagick;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use codewithkyle\jitter\Jitter;
use codewithkyle\JitterCore\Jitter;
use codewithkyle\jitter\exceptions\JitterException;

class Transform extends Component
{
    // Public Methods
    // =========================================================================

    public function clearS3BucketCache(string $dirname)
    {
        $settings = $this->getSettings();
        if ($settings != null && isset($settings['accessKey']) && isset($settings['secretAccessKey']) && isset($settings['region']) && isset($settings['bucket']))
        {
            $s3 = $this->connectToS3();
            $files = \scandir($dirname);
            foreach ($files as $key => $value)
            {
                if ($value != '.' && $value != '..')
                {
                    $key = "/" . str_replace('\\', '/', $value);
                    $key = preg_replace("/.*\//", '', $key);
                    if (isset($settings['folder']))
                    {
                        $uri = trim($settings['folder'], "/") . "/"  . ltrim($key, "/");
                    }
                    $s3->deleteObject([
                        'Bucket' => $settings['bucket'],
                        'Key'    => $key,
                    ]);
                    unlink(FileHelper::normalizePath($dirname . "/" . $value));
                }
            }
        }
    }

    public function generateSourceSet(string $id, array $images): string
    {        
        $masterImage = null;
        $ret = "";
        $baseUrl = "/jitter/v1/transform?id=" . $id;

        $asset = Asset::find()->id($id)->one();
        if (empty($asset))
        {
            Craft::error("Failed to find asset with an id of " . $id, __METHOD__);
        }
        else
        {
            $masterImage = $asset->getCopyOfFile();
        }
        if ($masterImage)
        {
            $count = 0;
            $maxCount = count($images);
            foreach ($images as $image)
            {
                $count++;
                $ret .= $baseUrl;
                foreach ($image as $key => $value)
                {
                    $ret .= "&" . $key . "=" . $value;
                }
                if (isset($image['w']))
                {
                    $ret .= " " . $image['w'] . "w";
                }
                else
                {
                    if (isset($image['h']))
                    {
                        $aspectRatioValues = [$asset->width, $asset->height];
                        if (isset($params['ar']))
                        {
                            $values = explode(':', $params['ar']);
                            if (count($values) == 2)
                            {
                                $aspectRatioValues = [intval($values[0]), intval($values[1])];
                            }
                        }
                        $height = intval($params['h']);
                        $width = ($aspectRatioValues[0] / $aspectRatioValues[1]) * $height;
                        $ret .= " " . $width . "w";
                    }
                    else
                    {
                        $ret .= " " . $asset->width . "w";
                    }
                }
                if ($count < $maxCount)
                {
                    $ret .= ", ";
                }
            }
        }
        return $ret;
    }

    public function transformImage(array $params, bool $clientAcceptsWebp)
    {
        $masterImage = null;
        $asset = null;
        $settings = this->getSettings();
        $useS3 = $settings != null;
        if (isset($params['id']))
        {
            $asset = Asset::find()->id($params['id'])->one();
            if (empty($asset))
            {
                $this->fail(404, "Image with id " . $params["id"] . " does not exist.");
            }
            else
            {
                $masterImage = $asset->getCopyOfFile();
            }
        }
        else if (isset($params['path']))
        {
            $masterImage = FileHelper::normalizePath(Yii::getAlias("@webroot") . "/" . ltrim($params['path'], "/"));
            if (!\file_exists($masterImage))
            {
                $this->fail(404, "Invalid image location: " . $masterImage);
            }
        }
        else
        {
            $this->fail(400, "'id' or 'path' required");
        }

        $filename = $masterImage->filename ?? $masterImage;
        preg_match("/(\..{1,4})$/", $asset->filename, $matches);
        $fallbackFormat = strtolower(ltrim($matches[0], "."));

        if (!empty($asset))
        {
            $width = $masterImage->width;
            $height = $masterImage->height;
        }
        else
        {
            $img = new Imagick($masterImage);
            $width = $img->getImageWidth();
            $height = $img->getImageHeight();
        }

        $transformSettings = Jitter::BuildTransform($params, $width, $height, $fallbackFormat);

        // $existingFile = $this->findExistingFile(Craft::$app->path->runtimePath, $filename, $transform['format'], $clientAcceptsWebp);

        $uid = StringHelper::UUID();
        $tempImage = FileHelper::normalizePath($this->getTempPath() . "/" . $uid . "tmp");

        $assetPath = $masterImage->getPath() ?? $masterImage;
        \copy($assetPath, $tempImage);

        $resizeOn = "width";
        if (isset($params["h"]) && !isset($params["w"]))
        {
            $resizeOn = "height";
        }
        Jitter::TransformImage($tempImage, $transform, $resizeOn);

        $key = $this->buildTransformUid($transformSettings);

        if ($useS3)
        {
            $s3 = $this->connectToS3($settings);
            $file = $s3->putObject([
                'Bucket' => $settings['bucket'],
                'Key' => $key,
                'SourceFile' => $tempImage,
            ]);
            touch(FileHelper::normalizePath($this->getTempPath() . "/" . $key));
        }
        else
        {
            copy($tempImage, FileHelper::normalizePath($this->getPublicPath() . "/" . $key));
            $file = [
                "Body" => file_get_contents($tempImage),
                "ContentType" => mime_content_type($tempImage),
            ];
        }

        \unlink($tempImage);

        return $file;
    }

    private function buildTransformUid(array $transform): string
    {
        $key = $transform['width'] . "-" . $transform['height'] . "-" . $transform['focusPoint'][0] . "-" . $transform['focusPoint'][1] . "-" . $transform["quality"] . "-" . $transform['background'] . "-" . $transform['mode'];
        return \md5($key);
    }

    private function fail(int $statusCode, string $error): void
    {
        throw new JitterException($statusCode, $error);
    }

    private function getTempPath(): string
	{
		$path = FileHelper::normalizePath(Craft::$app->path->runtimePath . '/jitter');
		if (!file_exists($path)) {
			mkdir($path);
		}
		return $path;
	}

    private function connectToS3(arrya $settings)
    {
        return S3Client::factory([
            'credentials' => [
                'key'    => $settings['accessKey'],
                'secret' => $settings['secretAccessKey'],
            ],
            'region' => $settings['region'],
            'version' => 'latest'
        ]);
    }

    private function getSettings()
    {
        $settings = null;
        $settingsPath = FileHelper::normalizePath(Craft::$app->path->configPath . '/jitter.php');
        if (\file_exists($settingsPath))
        {
            $settings = include($settingsPath);
        }
        return $settings;
    }

    private function getPublicPath(): string
    {
        $path = FileHelper::normalizePath(Yii::getAlias("@webroot") . '/jitter');
        if (!file_exists($path))
        {
            mkdir($path);
        }
        return $path;
    }
}
