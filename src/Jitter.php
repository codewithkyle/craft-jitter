<?php
/**
 * Jitter plugin for Craft CMS 4.x
 *
 * A just in time image transformation service.
 *
 * @link      https://kyleandrews.dev/
 * @copyright Copyright (c) 2022 Kyle Andrews
 */

namespace codewithkyle\jitter;

use codewithkyle\jitter\services\Transform as TransformService;
use codewithkyle\jitter\variables\JitterVariable;

use Craft;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\utilities\ClearCaches;
use craft\events\RegisterCacheOptionsEvent;
use craft\helpers\FileHelper;

use Yii;
use yii\base\Event;

/**
 * @author    Kyle Andrews
 * @package   Jitter
 * @since     1.0.0
 *
 * @property  TransformService $transform
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class Jitter extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Jitter::$plugin
     *
     * @var Jitter
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('jitter', JitterVariable::class);
            }
        );

        // Register our site routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['/jitter/v1/transform'] = 'jitter/transform/image';
            }
        );

        // Register cache busting utility
        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function (RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'jitter-transform-cache',
                    'label' => Craft::t('jitter', 'Transformed images'),
                    'action' => function() {
                        Jitter::getInstance()->transform->clearS3BucketCache();

                        $publicDir = FileHelper::normalizePath(Yii::getAlias('@webroot') . "/jitter");
                        if (\file_exists($publicDir))
                        {
                            array_map('unlink', glob("$publicDir/*"));
                        }
                    }
                ];
            }
        );

        // Register delete event logic
        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_DELETE,
            function ($e) {
                $asset = $e->sender;
                Jitter::getInstance()->transform->clearImageTransforms($asset);
            }
        );

        Craft::info(
            Craft::t(
                'jitter',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }
}
