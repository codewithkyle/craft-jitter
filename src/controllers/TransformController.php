<?php
/**
 * JITIT plugin for Craft CMS 3.x
 *
 * A just in time image transformation service.
 *
 * @link      https://kyleandrews.dev/
 * @copyright Copyright (c) 2020 Kyle Andrews
 */

namespace codewithkyle\jitit\controllers;

use codewithkyle\jitit\JITIT;

use Craft;
use craft\web\Controller;

/**
 * Transform Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Kyle Andrews
 * @package   JITIT
 * @since     1.0.0
 */
class TransformController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['image'];

    // Public Methods
    // =========================================================================

    public function actionImage()
    {
        $request = Craft::$app->getRequest();
        $params = $request->getQueryParams();
        $clientAcceptsWebp = $request->accepts('image/webp');
        $response = JITIT::getInstance()->transform->transformImage($params, $clientAcceptsWebp);
        if ($response['success'])
        {
            return $this->asJson($response);
        }
        else
        {
            return Craft::$app->getResponse()->setStatusCode(404);
        }
    }
}
