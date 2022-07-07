<?php
/**
 * Jitter plugin for Craft CMS 4.x
 *
 * A just in time image transformation service.
 *
 * @link      https://kyleandrews.dev/
 * @copyright Copyright (c) 2022 Kyle Andrews
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

    public function clearS3BucketCache(): void
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

    public function generateURL(array $params): string
    {
        $ret = "/jitter/v1/transform?";
        foreach ($params as $key => $value)
        {
            $ret .= $key . "=" . $value . "&";
        }
        return rtrim($ret, "&");
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
            \unlink($masterImage);
        }
        return $ret;
    }

    public function transformImage(array $params, Asset $asset = null): array
    {
        $transform = JitterCore::BuildTransform($params);
        $key = $this->createKey($params, $asset);

        // Caching logic
        $cachedResponse = $this->checkCache($settings, $key);
        if (!empty($cachedResponse))
        {
            return $cachedResponse;
        }

        // Transform logic
        $masterImage = null;
        $settings = $this->getSettings();
        $needsCleanup = false; 

        if (!is_null($asset))
        {
            $masterImage = $asset->getCopyOfFile();
            $needsCleanup = true;
        }
        else if (isset($params["id"]))
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
                $this->fail(404, "Invalid image path.");
            }
        }
        else
        {
            $this->fail(400, "'id' or 'path' required.");
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

    private function buildTransformUid(string $uniqueValue, array $transform): string
    {
        return \md5($uniqueValue) . "-" . \md5(json_encode($transform));
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

    private function connectToS3(array $settings): S3Client
    {
        $conn = [
            'credentials' => [
                'key'    => $settings['accessKey'],
                'secret' => $settings['secretAccessKey'],
            ],
            'region' => $settings['region'],
            'version' => 'latest'
        ];
        if (isset($settings["endpoint"]))
        {
            $conn["endpoint"] = $settings["endpoint"];
        }
        return S3Client::factory($conn);
    }

    private function getSettings(): ?array
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
                $s3Key = $key;
                if (isset($settings['folder']))
                {
                    $s3Key = $settings['folder'] . "/" . $s3Key;
                }
                $file = $s3->getObject([
                    "Bucket" => $settings["bucket"],
                    "Key" => $s3Key,
                ]);
                $response["Body"] = $file["Body"];
                $response["Name"] = $key;
                $response["ContentType"] = $file["ContentType"];
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
        return (array)$response;
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

    private function createKey(array $params, Asset $asset): string
    {
        $assetIndent = null;
        if (!is_null($asset))
        {
            $assetIndent = $asset->id;
        }
        else if (isset($params["id"]))
        {
            $assetIndent = $params["id"];
        }
        else if (isset($params["path"]))
        {
            $assetIndent = $params["path"];
        }
        return $this->buildTransformUid($assetIndent, $params);
    }
}
