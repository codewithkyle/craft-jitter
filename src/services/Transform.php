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
    
    public function clearImageTransforms(Asset $image): void
    {
        $settings = $this->getSettings();
        $uid = \md5($image->id);
        if (!empty($settings))
        {
            $dirname = $this->getTempPath();
            $s3 = $this->connectToS3($settings);
            $files = \scandir($dirname);
            foreach ($files as $key => $value)
            {
                if ($value != '.' && $value != '..')
                {
                    $segments = explode("-", $value);
                    if ($segments[0] == $uid)
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
                        $filePath = FileHelper::normalizePath($dirname . "/" . $value);
                        \unlink($filePath);
                    }
                }
            }
        }
        else
        {
            $dirname = $this->getPublicPath();
            $files = \scandir($dirname);
            foreach ($files as $key => $value)
            {
                if ($value != '.' && $value != '..')
                {
                    $segments = explode("-", $value);
                    if ($segments[0] == $uid)
                    {
                        $filePath = FileHelper::normalizePath($dirname . "/" . $value);
                        \unlink($filePath);
                    }
                }
            }
        }
    }

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
        // Return early if we are using a CDN && the transform has already been created
        $settings = $this->getSettings();
        if (isset($settings["cdn"]))
        {
            $transform = JitterCore::BuildTransform($params);
            $key = $this->createKey($transform, $params["id"]);
            $path = FileHelper::normalizePath($this->getTempPath() . "/" . $key);
            if (\file_exists($path))
            {
                $ret = rtrim($settings["cdn"], "/") . "/";
                if (isset($settings["folder"]))
                {
                    $ret .= trim($settings["folder"], "/") . "/";
                }
                $ret .= $key;
                return $ret;
            }
        }

        // If not using CDN or the transform doesn't exist (yet) use local URL
        $ret = "/jitter/v1/transform?";
        foreach ($params as $key => $value)
        {
            $ret .= $key . "=" . $value . "&";
        }
        return rtrim($ret, "&");
    }

    public function generateSourceSet(string|int|Asset $assetOrId, array $images): string
    {        
        $ret = "";
        $id = $assetOrId;
        $asset = null;

        if ($assetOrId instanceof Asset)
        {
            $asset = $assetOrId;
            $id = $assetOrId->id;
        }
        else
        {
            $asset = Asset::find()->id($id)->one();
            if (empty($asset))
            {
                Craft::error("Failed to find asset with an id of " . $id, __METHOD__);
            }
        }

        $baseUrl = "/jitter/v1/transform?id=" . $id;
        $cdnUrl = null;
        $settings = $this->getSettings();
        if (isset($settings["cdn"]))
        {
            $cdnUrl = rtrim($settings["cdn"], "/") . "/";
        }

        if (!empty($asset))
        {
            $count = 0;
            $maxCount = count($images);
            foreach ($images as $image)
            {
                $count++;
                $usedCDN = false;

                if (!is_null($cdnUrl))
                {
                    $transform = JitterCore::BuildTransform($image);
                    $key = $this->createKey($transform, $id);
                    $path = FileHelper::normalizePath($this->getTempPath() . "/" . $key);
                    if (\file_exists($path))
                    {
                        $ret .= $cdnUrl;
                        if (isset($settings["folder"]))
                        {
                            $ret .= trim($settings["folder"], "/") . "/";
                        }
                        $ret .= $key;
                        $usedCDN = true;
                    }
                }
                if (!$usedCDN)
                {
                    $ret .= $baseUrl;
                    foreach ($image as $key => $value)
                    {
                        $ret .= "&" . $key . "=" . $value;
                    }
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
                        if (isset($image['ar']))
                        {
                            $values = explode(':', $image['ar']);
                            if (count($values) == 2)
                            {
                                $aspectRatioValues = [intval($values[0]), intval($values[1])];
                            }
                        }
                        $height = intval($image['h']);
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

    public function transformImage(array $params, ?Asset $asset = null): array
    {
        $transform = JitterCore::BuildTransform($params);
        $assetOrId = $asset;
        if (is_null($assetOrId))
        {
            if (isset($params["id"]))
            {
                $assetOrId = $params["id"];
            }
            else
            {
                $assetOrId = $params["path"];
            }
        }
        $key = $this->createKey($transform, $assetOrId);
        $settings = $this->getSettings();

        // Caching logic
        if ($this->checkCache($settings, $key))
        {
            return $this->getCachedImage($settings, $key);
        }

        // Transform logic
        $masterImage = null;
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

        $mime = \mime_content_type($tempImage);
        $this->cacheImage($settings, $key, $tempImage, $mime);

        $file = [
            "Body" => \file_get_contents($tempImage),
            "ContentType" => $mime,
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
        if (!file_exists($path))
        {
			mkdir($path);
		}
		return $path;
	}

    private function connectToS3(?array $settings): S3Client
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

    private function getCachedImage(?array $settings, string $key): array
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

    private function checkCache(?array $settings, string $key): bool
    {
        $isCached = false;
        if (!empty($settings))
        {
            $path = FileHelper::normalizePath($this->getTempPath() . "/" . $key);
            if (\file_exists($path))
            {
                $isCached = true;
            }
        }
        else
        {
            $path = FileHelper::normalizePath($this->getPublicPath() . "/" . $key);
            if (\file_exists($path))
            {
                $isCached = true;
            }
        }
        return $isCached;
    }

    private function cacheImage($settings, $key, $image, $mime): void
    {
        if (!empty($settings))
        {
            $s3 = $this->connectToS3($settings);
            if (isset($settings['folder']))
            {
                $s3Key = $settings['folder'] . "/" . $key;
            }
            $s3->putObject([
                "Bucket" => $settings['bucket'],
                "Key" => $s3Key,
                "SourceFile" => $image,
                "ContentType" => $mime,
                "ACL" => $settings["acl"] ?? "private",
            ]);
            touch(FileHelper::normalizePath($this->getTempPath() . "/" . $key));
        }
        else
        {
            copy($image, FileHelper::normalizePath($this->getPublicPath() . "/" . $key));
        }
    }

    private function createKey(array $transform, Asset|string $assetOrId): string
    {
        $id = null;
        if ($assetOrId instanceof Asset)
        {
            $id = $assetOrId->id;
        }
        else
        {
            $id = $assetOrId;
        }
        return $this->buildTransformUid($id, $transform);
    }
}
