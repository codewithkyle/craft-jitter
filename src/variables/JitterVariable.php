<?php
/**
 * Jitter plugin for Craft CMS 3.x
 *
 * A just in time image transformation service.
 *
 * @link      https://kyleandrews.dev/
 * @copyright Copyright (c) 2020 Kyle Andrews
 */

namespace codewithkyle\jitter\variables;

use codewithkyle\jitter\Jitter;

use Craft;
use craft\elements\Asset;

/**
 * Jitter Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.jITIT }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Kyle Andrews
 * @package   Jitter
 * @since     1.0.0
 */
class JitterVariable
{
    // Public Methods
    // =========================================================================

    public function transformImage(Asset $file, $params): string
    {
        $request = Craft::$app->getRequest();
        $clientAcceptsWebp = $request->accepts('image/webp');
        $params = json_decode(json_encode($params), true);
        $params['id'] = $file->id;
        $response = Jitter::getInstance()->transform->transformImage($params, $clientAcceptsWebp);
        if ($response['success'])
        {
            return $response['url'];
        }
        else
        {
            Craft::error($response['error'], __METHOD__);
        }
    }

    public function srcset(Asset $file, array $params): string
    {
        $images = json_decode(json_encode($params), true);
        return Jitter::getInstance()->transform->generateSourceSet($file->id, $images);
    }
}
