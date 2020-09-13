<?php
/**
 * JITIT plugin for Craft CMS 3.x
 *
 * A just in time image transformation service.
 *
 * @link      https://kyleandrews.dev/
 * @copyright Copyright (c) 2020 Kyle Andrews
 */

namespace codewithkyle\jitit\services;

use codewithkyle\jitit\JITIT;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use Imagine\Imagick\Imagick;
use craft\helpers\FileHelper;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use Yii;

/**
 * Transform Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Kyle Andrews
 * @package   JITIT
 * @since     1.0.0
 */
class Transform extends Component
{
    // Public Methods
    // =========================================================================

    public function transformImage(array $params, bool $clientAcceptsWebp): array
    {
        $response = [
            'success' => true,
            'error' => null,
            'url' => null,
        ];
        
        $masterImage = null;
        if (isset($params['id']))
        {
            $asset = Asset::find()->id($params['id'])->one();
            if (empty($asset))
            {
                $response['success'] = false;
                $response['error'] = 'Failed to find asset with an id of ' . $params['id'];
                return $response;
            }
            else
            {
                $masterImage = $asset->url;
            }
        }
        else if (isset($params['path']))
        {
            $masterImage = $params['path'];
        }

        $settings = include(FileHelper::normalizePath(Craft::$app->path->configPath . '/jitit.php')) ?? null;
        $s3 = null;
        if (!empty($settings))
        {
            $s3 = S3Client::factory([
                'credentials' => [
                    'key'    => $settings['accessKey'],
                    'secret' => $settings['secretAccessKey'],
                ],
                'region' => $settings['region'],
                'version' => 'latest'
            ]);
        }

        $helper = new \craft\helpers\StringHelper();
        $uid = str_replace('-', '', $helper->UUID());
        preg_match("/(\..*)$/", $asset->filename, $matches);
        $baseType = $matches[0];

        $transform = $this->getImageTransformSettings($params, $masterImage);
        $filename = preg_replace("/(\..*)$/", '', $asset->filename) . '-' . $transform['width'] . '-' . $transform['height'];

        // Quickly respond with existing files
        if ($s3)
        {
            $existingFile = $this->findExistingFile(Craft::$app->path->runtimePath, $filename, $transform['format'], $clientAcceptsWebp);   
            if ($existingFile){
                $uri = "/" . str_replace('\\', '/', $existingFile);
                $uri = preg_replace("/.*\//", '', $uri);
                if (isset($settings['folder']))
                {
                    $uri = trim($settings['folder'], "/") . '/' . ltrim($uri, "/");
                }
                $response['url'] = $s3->getObjectUrl($settings['bucket'], $uri);
                return $response;
            }
        }
        else
        {
            $existingFile = $this->findExistingFile(Yii::getAlias('@webroot'), $filename, $transform['format'], $clientAcceptsWebp);
            if ($existingFile){
                $cleanName = "/" . str_replace('\\', '/', $existingFile);
                $cleanName = preg_replace("/.*\//", '', $cleanName);
                $response['url'] = "/jitit/" . $cleanName;
                return $response;
            }
        }

        // Do the thing
        $tempImage = $this->transform($masterImage, $baseType, $uid, $transform);
        $finalImage = $this->convertImage($tempImage, $filename, $baseType, $clientAcceptsWebp, $transform);

        // Save the output
        if ($s3)
        {
            preg_match("/(\..*)$/", $finalImage, $matches);
            $finalImageType = $matches[0];
            $uri = "/" . str_replace('\\', '/', $finalImage);
            $uri = preg_replace("/.*\//", '', $uri);
            if (isset($settings['folder']))
            {
                $uri = trim($settings['folder'], "/") . '/' . ltrim($uri, "/");
            }
            $s3Response = $s3->putObject([
                'Bucket' => $settings['bucket'],
                'Key' => $uri,
                'SourceFile' => $finalImage,
                'ACL' => 'public-read',
            ]);
            $jititCachePath = FileHelper::normalizePath(Craft::$app->path->runtimePath . '/jitit');
            if (!file_exists($jititCachePath))
            {
                mkdir($jititCachePath);
            }
            touch(FileHelper::normalizePath($jititCachePath . '/' . $filename. $finalImageType));
            $response['url'] = $s3Response['ObjectURL'];
        }
        else
        {
            $publicPath = FileHelper::normalizePath(Yii::getAlias("@webroot") . '/jitit');
            if (!file_exists($publicPath))
            {
                mkdir($publicPath);
            }
            $cleanName = "/" . str_replace('\\', '/', $finalImage);
            $cleanName = preg_replace("/.*\//", '', $cleanName);
            copy($finalImage, FileHelper::normalizePath($publicPath. "/" . $cleanName));
            $response['url'] = "/jitit/" . $cleanName;
        }

        unlink($tempImage);
        unlink($finalImage);

        return $response;
    }

    private function findExistingFile(string $path, string $filename, string $format, bool $clientAcceptsWebp)
    {
        $fileTypes = ['webp', 'png', 'jpg', 'gif'];
        $existingFile = null;
        foreach ($fileTypes as $fileType)
        {
            $file = FileHelper::normalizePath($path . '/jitit/' . $filename . "." . $fileType);
            if (file_exists($file))
            {
                if ($format != 'auto')
                {
                    if ($format == $fileType)
                    {
                        $existingFile = $file;
                        break;
                    }
                }
                else
                {
                    if ($fileType != 'webp')
                    {
                        $existingFile = $file;
                        break;
                    }
                    else if ($clientAcceptsWebp)
                    {
                        $existingFile = $file;
                        break;
                    }
                }
            }
        }
        return $existingFile;
    }

    private function convertImage(string $tempImage, string $filename, string $baseType, bool $clientAcceptsWebp, array $transform): string
    {
        $tempPath = Craft::$app->path->tempPath;
        $img = new Imagick($tempImage);
        $finalImage = null;
        switch ($transform['format'])
        {
            case "jpg":
                $img->setImageFormat("jpeg");
                $img->setImageCompressionQuality($transform['quality']);
                $finalImage = $tempPath . "/" . $filename . ".jpg";
                $img->writeImage($finalImage);
                break;
            case "gif":
                $img->setImageFormat("gif");
                $img->setImageCompressionQuality($transform['quality']);
                $finalImage = $tempPath . "/" . $filename . ".gif";
                $img->writeImage($finalImage);
                break;
            case "png":
                $img->setImageFormat("png");
                $img->setImageCompressionQuality($transform['quality']);
                $finalImage = $tempPath . "/" . $filename . ".png";
                $img->writeImage($finalImage);
                break;
            default:
                if ($clientAcceptsWebp && (\count(\Imagick::queryFormats('WEBP')) > 0) || $clientAcceptsWebp && file_exists("/usr/bin/cjpeg"))
                {
                    if ((\count(\Imagick::queryFormats('WEBP')) > 0))
                    {
                        $img->setImageFormat("webp");
                        $img->setImageCompressionQuality($transform['quality']);
                        $finalImage = $tempPath . "/" . $filename . ".webp";
                        $img->writeImage($finalImage);
                    }
                    else if (file_exists("/usr/bin/cwebp"))
                    {
                        $finalImage = $tempPath . "/" . $filename . '.webp';
                        $command = escapeshellcmd("/usr/bin/cwebp -q " . $transform['quality'] . " " . $tempImage . " -o " . $finalImage);
                        shell_exec($command);
                    }
                }
                else 
                {
                    switch ($baseType)
                    {
                        case "jpg":
                            $img->setImageFormat("jpeg");
                            $img->setImageCompressionQuality($transform['quality']);
                            $finalImage = $tempPath . "/" . $filename . ".jpg";
                            $img->writeImage($finalImage);
                            break;
                        case "jpeg":
                            $img->setImageFormat("jpeg");
                            $img->setImageCompressionQuality($transform['quality']);
                            $finalImage = $tempPath . "/" . $filename . ".jpg";
                            $img->writeImage($finalImage);
                            break;
                        case "gif":
                            $img->setImageFormat("gif");
                            $img->setImageCompressionQuality($transform['quality']);
                            $finalImage = $tempPath . "/" . $filename . ".gif";
                            $img->writeImage($finalImage);
                        break;
                        default:
                            $img->setImageFormat("png");
                            $img->setImageCompressionQuality($transform['quality']);
                            $finalImage = $tempPath . "/" . $filename . ".png";
                            $img->writeImage($finalImage);
                            break;
                    }
                    break;
                }
                break;
        }
        return $finalImage;
    }

    private function transform(string $path, string $baseType, string $uid, array $transform): string
    {
        $img = new Imagick($path);

        switch ($transform['mode'])
        {
            case "resize":
                $img->thumbnailImage($transform['width'], $transform['height'], false, false);
                break;
            case "letterbox":
                $img->setImageBackgroundColor('#' . $transform['background']);
                $img->thumbnailImage($transform['width'], $transform['height'], true, true);
                break;
            default:
                $img->cropThumbnailImage($transform['width'], $transform['height']);
                break;
        }

        $tempPath = Craft::$app->path->tempPath;
        $tempImage = $tempPath . "/" . $uid . $baseType;
        $img->writeImage($tempImage);
        return $tempImage;
    }

    private function getImageTransformSettings(array $params, string $path): array
    {
        $img = new Imagick($path);

        $aspectRatioValues = [$img->getImageWidth(), $img->getImageHeight()];
        if (isset($params['ar']))
        {
            $values = explode(':', $params['ar']);
            if (count($values) == 2)
            {
                $aspectRatioValues = [intval($values[0]), intval($values[1])];
            }
        }

        $width = $img->getImageWidth();
        $height = $img->getImageHeight();
        if (isset($params['w']) && isset($params['h']))
        {
            $width = intval($params['w']);
            $height = intval($params['h']);
        }
        else if (isset($params['w']))
        {
            $width = intval($params['w']);
            $height = ($aspectRatioValues[1] / $aspectRatioValues[0]) * $width;
        }
        else if (isset($params['h']))
        {
            $height = intval($params['h']);
            $width = ($aspectRatioValues[0] / $aspectRatioValues[1]) * $height;
        }
        
        $quality = 80;
        if (isset($params['q']))
        {
            $quality = intval($params['q']);
        }

        $mode = 'crop';
        if (isset($params['m']))
        {
            $mode = $params['m'];
        }
        if ($mode == 'crop')
        {
            if ($width > $img->getImageWidth())
            {
                $width = $img->getImageWidth();
            }
            if ($height > $img->getImageHeight())
            {
                $height = $img->getImageHeight();
            }
        }

        $bg = 'ffffff';
        if (isset($params['bg']))
        {
            $bg = ltrim($params['bg'], '#');
        }

        $transform = [
            'width' => round($width),
            'height' => round($height),
            'format' => $params['fm'] ?? 'auto',
            'mode' => $mode,
            'quality' => $quality,
            'background' => $bg,
        ];
        return $transform;
    }
}
