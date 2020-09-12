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

    public function transformImage(array $params): array
    {
        $response = [
            'success' => true,
            'error' => null,
            'url' => null,
        ];
        
        if (isset($params['id']))
        {
            $asset = Asset::find()->id($params['id'])->one();
            if (!empty($asset))
            {
                $master = $asset->url;

                $img = new Imagick($master);

                $transform = $this->getImageTransformSettings($params, $img);

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

                $img->setImageCompressionQuality($transform['quality']);

                $tempPath = Craft::$app->path->tempPath;
                $img->writeImage($tempPath . "/test.jpg");

                die();
            }
            else
            {
                $response['success'] = false;
                $response['error'] = 'Failed to find asset with an id of ' . $params['id'];
            }
        }
        else{
            $response['success'] = false;
            $response['error'] = 'Missing required id parameter.';
        }

        return $response;
    }

    private function getImageTransformSettings(array $params, $img): array
    {
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

        $transform = [
            'width' => round($width),
            'height' => round($height),
            'format' => $params['fm'] ?? 'auto',
            'mode' => $mode,
            'quality' => $quality,
            'background' => $params['bg'] ?? 'ffffff',
        ];
        return $transform;
    }
}
