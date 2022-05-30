<?php
/**
 * Jitter plugin for Craft CMS 3.x
 *
 * A just in time image transformation service.
 *
 * @link      https://kyleandrews.dev/
 * @copyright Copyright (c) 2020 Kyle Andrews
 */

namespace codewithkyle\jitter\controllers;

use codewithkyle\jitter\Jitter;

use Craft;
use craft\web\Controller;
use craft\helpers\FileHelper;
use Yii;
use yii\web\Response;
use codewithkyle\jitter\exceptions\JitterException;

class TransformController extends Controller
{

    // Protected Properties
    // =========================================================================

    protected $allowAnonymous = ["image"];

    // Public Methods
    // =========================================================================

    public function actionImage(): Response
    {
        $request = Craft::$app->getRequest();
        $params = $request->getQueryParams();
        $clientAcceptsWebp = $request->accepts('image/webp');
        try
        {
            $file = Jitter::getInstance()->transform->transformImage($params, $clientAcceptsWebp);
            $response = Craft::$app->getResponse();
            $response->format = Response::FORMAT_RAW;
            if (isset($file["ContentType"]))
            {
                $response->headers->set("Content-Type", $file["ContentType"]);
            }
            return $response->sendContentAsFile($file["Body"], $file["Name"], ["inline" => true]);
        }
        catch (JitterException $e)
        {
            Craft::$app->getResponse()->setStatusCode($e->getStatusCode());
			return $e->getMessage();
        }
    }
}
