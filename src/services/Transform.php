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
use codewithkyle\JitterCore\Jitter as JitterCore;
use codewithkyle\jitter\exceptions\JitterException;

class Transform extends Component
{
    // Public Methods
    // =========================================================================

    public function clearS3BucketCache()
    {
        $settings = $this->getSettings();
        if (!empty($settings))
        {
            $dirname = $this->getTempPath();
            if (\file_exists($dirname))
            {
                $s3 = $this->connectToS3($settings);
                $files = \scandir($dirname);
                foreach ($files as $key => $value)
                {
                    if ($value != '.' && $value != '..')
                    {
                        $s3Key = $value;
                        if (isset($settings['folder']))
                        {
                            $s3Key = $settings['folder'] . "/" . $value;
                        }
                        $s3->deleteObject([
                            'Bucket' => $settings['bucket'],
                            'Key'    => $s3Key,
                        ]);
                        unlink(FileHelper::normalizePath($dirname . "/" . $value));
                    }
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
        $settings = $this->getSettings();
        $needsCleanup = false;
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
                $needsCleanup = true;
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

        preg_match("/(\..{1,4})$/", $masterImage, $matches);
        $fallbackFormat = strtolower(ltrim($matches[0], "."));

        $img = new Imagick($masterImage);
        $width = $img->getImageWidth();
        $height = $img->getImageHeight();

        $transform = JitterCore::BuildTransform($params, $width, $height, $fallbackFormat);
        $key = $this->buildTransformUid($transform, $asset->uid ?? $masterImage);

        $cachedResponse = $this->checkCache($settings, $key);
        if (!empty($cachedResponse))
        {
            return $cachedResponse;
        }

        $uid = StringHelper::UUID();
        $tempImage = FileHelper::normalizePath($this->getTempPath() . "/" . $uid . ".tmp");
        \copy($masterImage, $tempImage);
        if ($needsCleanup)
        {
            \unlink($masterImage);
        }

        $resizeOn = "width";
        if (isset($params["h"]) && !isset($params["w"]))
        {
            $resizeOn = "height";
        }
        JitterCore::TransformImage($tempImage, $transform, $resizeOn);

        $this->cacheImage($settings, $key, $tempImage);

        $file = [
            "Body" => \file_get_contents($tempImage),
            "ContentType" => \mime_content_type($tempImage),
            "Name" => $key,
        ];

        \unlink($tempImage);

        return $file;
    }

    private function buildTransformUid(array $transform, string $uniqueValue): string
    {
        $key = $uniqueValue . json_encode($transform);
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

    private function connectToS3(array $settings)
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
            if (isset($settings["folder"]))
            {
                $settings["folder"] = trim($settings["folder"], "/");
            }
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

    private function checkCache($settings, string $key): array
    {
        $response = [];
        if (!empty($settings))
        {
            $path = FileHelper::normalizePath($this->getTempPath() . "/" . $key);
            if (\file_exists($path))
            {
                $s3 = $this->connectToS3($settings);
                if (isset($settings['folder']))
                {
                    $key = $settings['folder'] . "/" . $key;
                }
                $response = $s3->getObject([
                    "Bucket" => getenv("S3_BUCKET"),
                    "Key" => $key,
                ]);
            }
        }
        else
        {
            $path = FileHelper::normalizePath($this->getPublicPath() . "/" . $key);
            if (\file_exists($path))
            {
                $response["Body"] = \file_get_contents($path);
                $response["Name"] = $key;
                $response["ContentType"] = \mime_content_type($path);
            }
        }
        return $response;
    }

    private function cacheImage($settings, $key, $image): void
    {
        if (!empty($settings))
        {
            $s3 = $this->connectToS3($settings);
            if (isset($settings['folder']))
            {
                $s3Key = $settings['folder'] . "/" . $key;
            }
            $s3->putObject([
                'Bucket' => $settings['bucket'],
                'Key' => $s3Key,
                'SourceFile' => $image,
            ]);
            touch(FileHelper::normalizePath($this->getTempPath() . "/" . $key));
        }
        else
        {
            copy($image, FileHelper::normalizePath($this->getPublicPath() . "/" . $key));
        }
    }
}
