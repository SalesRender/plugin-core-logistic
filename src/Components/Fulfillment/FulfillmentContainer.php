<?php
/**
 * Created for plugin-core-logistic
 * Date: 18.01.2024
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Fulfillment;

use SalesRender\Plugin\Core\Logistic\Components\Fulfillment\Exception\FulfillmentContainerException;

final class FulfillmentContainer
{

    private static FulfillmentSyncHandlerInterface $handler;

    private function __construct() {}

    public static function config(FulfillmentSyncHandlerInterface $handler): void
    {
        self::$handler = $handler;
    }

    public static function getHandler(): FulfillmentSyncHandlerInterface
    {
        if (!isset(self::$handler)) {
            throw new FulfillmentContainerException('Fulfillment container handler was not configured');
        }

        return self::$handler;
    }

}