<?php
/**
 * Created for plugin-core-logistic
 * Date: 17.01.2024
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Helpers;

use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Purpose\LogisticPluginClass;

class LogisticHelper
{
    private static bool $sortTrackStatuses = true;

    public static function config(bool $sortTrackStatues = true)
    {
        self::$sortTrackStatuses = $sortTrackStatues;
    }

    public static function isFulfillment(): bool
    {
        $extra = Info::getInstance()->getExtra();
        $class = $extra['class'] ?? '';
        return $class === LogisticPluginClass::CLASS_FULFILLMENT;
    }

    public static function isSortTrackStatuses(): bool
    {
        return self::$sortTrackStatuses;
    }

}