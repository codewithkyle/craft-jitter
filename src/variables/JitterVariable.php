<?php
/**
 * Jitter plugin for Craft CMS 4.x
 *
 * A just in time image transformation service.
 *
 * @link      https://kyleandrews.dev/
 * @copyright Copyright (c) 2022 Kyle Andrews
 */

namespace codewithkyle\jitter\variables;

use codewithkyle\jitter\Jitter;
use codewithkyle\jitter\exceptions\JitterException;

use Craft;
use craft\elements\Asset;


/**
 * Jitter Variable
 *
 * @author    Kyle Andrews
 * @package   Jitter
 * @since     1.0.0
 */
class JitterVariable
{
    // Public Methods
    // =========================================================================

    public function transformImage(Asset $file, array $params): string
    {
        $url = "";
        try
        {
            $params = json_decode(json_encode($params), true); // Convert objects to arrays
            $params['id'] = $file->id;
            $file = Jitter::getInstance()->transform->transformImage($params);
            $url = Jitter::getInstance()->transform->generateURL($params);
        }
        catch (JitterException $e)
        {
            Craft::error($e->getMessage(), __METHOD__);
        }
        return $url;
    }

    public function url(Asset $file, array $params): string
    {
        $params = json_decode(json_encode($params), true); // Convert objects to arrays
        $params["id"] = $file->id;
        return Jitter::getInstance()->transform->generateURL($params);
    }

    public function srcset(Asset $file, array $params): string
    {
        $params = json_decode(json_encode($params), true); // Convert objects to arrays
        return Jitter::getInstance()->transform->generateSourceSet($file->id, $params);
    }
}
