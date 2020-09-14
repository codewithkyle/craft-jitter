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

use codewithkyle\jitter\Jitter;

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
 * @package   Jitter
 * @since     1.0.0
 */
class Transform extends Component
{
    // Public Methods
    // =========================================================================

    public function clearS3BucketCache(string $dirname)
    {
        $settings = [];
        $settingsPath = FileHelper::normalizePath(Craft::$app->path->configPath . '/jitter.php');
        if (\file_exists($settingsPath))
        {
            $settings = include($settingsPath);
        }
        if (isset($settings['accessKey']) && isset($settings['secretAccessKey']) && isset($settings['region']) && isset($settings['bucket']))
        {
            $s3 = S3Client::factory([
                'credentials' => [
                    'key'    => $settings['accessKey'],
                    'secret' => $settings['secretAccessKey'],
                ],
                'region' => $settings['region'],
                'version' => 'latest'
            ]);

            $files = \scandir($dirname);
            foreach ($files as $key => $value)
            {
                if ($value != '.' && $value != '..')
                {
                    $uri = "/" . str_replace('\\', '/', $value);
                    $uri = preg_replace("/.*\//", '', $uri);
                    if (isset($settings['folder']))
                    {
                        $uri = trim($settings['folder'], "/") . "/"  . ltrim($uri, "/");
                    }
                    $s3->deleteObject([
                        'Bucket' => $settings['bucket'],
                        'Key'    => $uri,
                    ]);
                    unlink(FileHelper::normalizePath($dirname . "/" . $value));
                }
            }

            rmdir($dirname);
        }
    }

    public function generateSourceSet(string $id, array $images): string
    {        
        $masterImage = null;
        $ret = "";
        $baseUrl = "/actions/jitter/transform/image?id=" . $id;

        $asset = Asset::find()->id($id)->one();
        if (empty($asset))
        {
            Craft::error("Failed to find asset with an id of " . $id, __METHOD__);
        }
        else
        {
            $masterImage = $asset->url;
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

    public function transformImage(array $params, bool $clientAcceptsWebp): array
    {
        $response = [
            'success' => true,
            'error' => null,
            'url' => null,
            'type' => null,
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

        preg_match("/(\..*)$/", $asset->filename, $matches);
        $baseType = strtolower(ltrim($matches[0], "."));

        // Build transform details
        $transform = $this->getImageTransformSettings($params, $masterImage);
        $uid = $this->buildTransformUid($transform);
        $filename = preg_replace("/(\..*)$/", '', $asset->filename) . '-' . $uid;

        // Create S3 client (if possible)
        $settings = [];
        $settingsPath = FileHelper::normalizePath(Craft::$app->path->configPath . '/jitter.php');
        if (\file_exists($settingsPath))
        {
            $settings = include($settingsPath);
        }
        $s3 = null;
        if (isset($settings['accessKey']) && isset($settings['secretAccessKey']) && isset($settings['region']) && isset($settings['bucket']))
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

        // Quickly respond with existing files
        if ($s3)
        {
            $existingFile = $this->findExistingFile(Craft::$app->path->runtimePath, $filename, $transform['format'], $clientAcceptsWebp);   
            if ($existingFile){
                $uri = "/" . str_replace('\\', '/', $existingFile);
                $uri = preg_replace("/.*\//", '', $uri);
                if (isset($settings['folder']))
                {
                    $uri = trim($settings['folder'], "/") . "/"  . ltrim($uri, "/");
                }
                $response['url'] = $s3->getObjectUrl($settings['bucket'], $uri);
                $response['type'] = 'external';
                return $response;
            }
        }
        else
        {
            $existingFile = $this->findExistingFile(Yii::getAlias('@webroot'), $filename, $transform['format'], $clientAcceptsWebp);
            if ($existingFile){
                $cleanName = DIRECTORY_SEPARATOR . str_replace('\\', '/', $existingFile);
                $cleanName = preg_replace("/.*\//", '', $cleanName);
                $response['url'] = "/jitter/" . $cleanName;
                $response['type'] = 'local';
                return $response;
            }
        }

        // Do the things
        $tempImage = $this->transform($masterImage, $baseType, $transform, $params);
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
                $uri = trim($settings['folder'], "/") . "/" . ltrim($uri, "/");
            }
            $s3Response = $s3->putObject([
                'Bucket' => $settings['bucket'],
                'Key' => $uri,
                'SourceFile' => $finalImage,
                'ACL' => 'public-read',
            ]);
            $jitterCachePath = FileHelper::normalizePath(Craft::$app->path->runtimePath . '/jitter');
            if (!file_exists($jitterCachePath))
            {
                mkdir($jitterCachePath);
            }
            touch(FileHelper::normalizePath($jitterCachePath . DIRECTORY_SEPARATOR . $filename. $finalImageType));
            $response['url'] = $s3Response['ObjectURL'];
            $response['type'] = 'external';
        }
        else
        {
            $publicPath = FileHelper::normalizePath(Yii::getAlias("@webroot") . '/jitter');
            if (!file_exists($publicPath))
            {
                mkdir($publicPath);
            }
            $cleanName = DIRECTORY_SEPARATOR . str_replace('\\', '/', $finalImage);
            $cleanName = preg_replace("/.*\//", '', $cleanName);
            copy($finalImage, FileHelper::normalizePath($publicPath. DIRECTORY_SEPARATOR  . $cleanName));
            $response['url'] = "/jitter/" . $cleanName;
            $response['type'] = 'local';
        }

        // Cleanup
        unlink($tempImage);
        unlink($finalImage);

        return $response;
    }

    private function buildTransformUid(array $transform): string
    {
        $key = $transform['width'] . "-" . $transform['height'] . "-" . $transform['focusPoint'][0] . "-" . $transform['focusPoint'][1] . "-" . $transform["quality"] . "-" . $transform['background'] . "-" . $transform['mode'];
        return \md5($key);
    }

    private function findExistingFile(string $path, string $filename, string $format, bool $clientAcceptsWebp)
    {
        $fileTypes = ['webp', 'png', 'jpg', 'gif'];
        $existingFile = null;
        foreach ($fileTypes as $fileType)
        {
            $file = FileHelper::normalizePath($path . '/jitter/' . $filename . "." . $fileType);
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
                $finalImage = $tempPath . DIRECTORY_SEPARATOR . $filename . ".jpg";
                $img->writeImage($finalImage);
                break;
            case "gif":
                $img->setImageFormat("gif");
                $img->setImageCompressionQuality($transform['quality']);
                $finalImage = $tempPath . DIRECTORY_SEPARATOR . $filename . ".gif";
                $img->writeImage($finalImage);
                break;
            case "png":
                $img->setImageFormat("png");
                $img->setImageCompressionQuality($transform['quality']);
                $finalImage = $tempPath . DIRECTORY_SEPARATOR . $filename . ".png";
                $img->writeImage($finalImage);
                break;
            default:
                if ($clientAcceptsWebp && (\count(\Imagick::queryFormats('WEBP')) > 0) || $clientAcceptsWebp && file_exists("/usr/bin/cjpeg"))
                {
                    if ((\count(\Imagick::queryFormats('WEBP')) > 0))
                    {
                        $img->setImageFormat("webp");
                        $img->setImageCompressionQuality($transform['quality']);
                        $finalImage = $tempPath . DIRECTORY_SEPARATOR . $filename . ".webp";
                        $img->writeImage($finalImage);
                    }
                    else if (file_exists("/usr/bin/cwebp"))
                    {
                        $finalImage = $tempPath . DIRECTORY_SEPARATOR . $filename . '.webp';
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
                            $finalImage = $tempPath . DIRECTORY_SEPARATOR . $filename . ".jpg";
                            $img->writeImage($finalImage);
                            break;
                        case "jpeg":
                            $img->setImageFormat("jpeg");
                            $img->setImageCompressionQuality($transform['quality']);
                            $finalImage = $tempPath . DIRECTORY_SEPARATOR . $filename . ".jpg";
                            $img->writeImage($finalImage);
                            break;
                        case "gif":
                            $img->setImageFormat("gif");
                            $img->setImageCompressionQuality($transform['quality']);
                            $finalImage = $tempPath . DIRECTORY_SEPARATOR . $filename . ".gif";
                            $img->writeImage($finalImage);
                        break;
                        default:
                            $img->setImageFormat("png");
                            $img->setImageCompressionQuality($transform['quality']);
                            $finalImage = $tempPath . DIRECTORY_SEPARATOR . $filename . ".png";
                            $img->writeImage($finalImage);
                            break;
                    }
                    break;
                }
                break;
        }
        return $finalImage;
    }

    private function transform(string $path, string $baseType, array $transform, array $params): string
    {
        $helper = new \craft\helpers\StringHelper();
        $uid = str_replace('-', '', $helper->UUID());

        $img = new Imagick($path);
        $img->setImageCompression(Imagick::COMPRESSION_NO);
        $img->setImageCompressionQuality(100);
        $img->setOption('png:compression-level', 9);

        switch ($transform['mode'])
        {
            case "fit":
                $img->resizeImage($transform['width'], $transform['height'], Imagick::FILTER_LANCZOS, 0.75);
                $tempPath = Craft::$app->path->tempPath;
                $tempImage = $tempPath . DIRECTORY_SEPARATOR . $uid . "." . $baseType;
                $img->writeImage($tempImage);
                break;
            case "letterbox":
                $img->setImageBackgroundColor('#' . $transform['background']);
                $img->resizeImage($transform['width'], $transform['height'], Imagick::FILTER_LANCZOS, 0.75, true);
                $tempPath = Craft::$app->path->tempPath;
                $tempImage = $tempPath . DIRECTORY_SEPARATOR . $uid . "." . $baseType;
                $img->writeImage($tempImage);
                break;
            case "crop":
                // Get focus points
                $leftPos = floor($img->getImageWidth() * $transform['focusPoint'][0]) - floor($transform['width'] / 2);
                $topPos = floor($img->getImageHeight() * $transform['focusPoint'][1]) - floor($transform['height'] / 2);

                // Step 2: crop
                $img->cropImage($transform['width'], $transform['height'], $leftPos, $topPos);

                $tempPath = Craft::$app->path->tempPath;
                $tempImage = $tempPath . DIRECTORY_SEPARATOR . $uid . "." . $baseType;
                $img->writeImage($tempImage);
                break;
            default:
                // Step 1: resize to best fit
                if (isset($params['w']) && isset($params['h']))
                {
                    $width = intval($params['w']);
                    $height = intval($params['h']);
                    $img->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 0.75);
                }
                else
                {
                    if ($transform['width'] > $transform['height'])
                    {
                        $img->resizeImage($transform['width'], null, Imagick::FILTER_LANCZOS, 0.75);
                    }
                    else
                    {
                        $img->resizeImage(null, $transform['height'], Imagick::FILTER_LANCZOS, 0.75);
                    }
                }

                // Get focus points
                $leftPos = floor($img->getImageWidth() * $transform['focusPoint'][0]) - floor($transform['width'] / 2);
                $topPos = floor($img->getImageHeight() * $transform['focusPoint'][1]) - floor($transform['height'] / 2);

                // Step 2: crop
                $img->cropImage($transform['width'], $transform['height'], $leftPos, $topPos);

                $tempPath = Craft::$app->path->tempPath;
                $tempImage = $tempPath . DIRECTORY_SEPARATOR . $uid . "." . $baseType;
                $img->writeImage($tempImage);
                break;
        }

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

        $mode = 'clip';
        if (isset($params['m']))
        {
            $mode = $params['m'];
        }

        $bg = 'ffffff';
        if (isset($params['bg']))
        {
            $bg = ltrim($params['bg'], '#');
        }

        $focusPoints = [0.5, 0.5];
        if (isset($params['fp-x']))
        {
            $focusPoints[0] = floatval($params['fp-x']);
            if ($focusPoints[0] < 0)
            {
                $focusPoints[0] = 0;
            }
            if ($focusPoints[0] > 1)
            {
                $focusPoints[0] = 1;
            }
        }
        if (isset($params['fp-y']))
        {
            $focusPoints[1] = floatval($params['fp-y']);
            if ($focusPoints[1] < 0)
            {
                $focusPoints[1] = 0;
            }
            if ($focusPoints[1] > 1)
            {
                $focusPoints[1] = 1;
            }
        }

        $transform = [
            'width' => round($width),
            'height' => round($height),
            'format' => $params['fm'] ?? 'auto',
            'mode' => $mode,
            'quality' => $quality,
            'background' => $bg,
            'focusPoint' => $focusPoints
        ];
        return $transform;
    }
}
