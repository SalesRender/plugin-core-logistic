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

    private static FulfillmentBindingHandlerInterface $bindingHandler;
    private static FulfillmentSyncHandlerInterface $syncHandler;
    private static FulfillmentRemoveHandlerInterface $removeHandler;

    private function __construct()
    {
    }

    public static function config(
        FulfillmentBindingHandlerInterface $bindingHandler,
        FulfillmentSyncHandlerInterface    $syncHandler,
        FulfillmentRemoveHandlerInterface  $removeHandler
    ): void
    {
        self::$bindingHandler = $bindingHandler;
        self::$syncHandler = $syncHandler;
        self::$removeHandler = $removeHandler;
    }

    public static function getBindingHandler(): FulfillmentBindingHandlerInterface
    {
        if (!isset(self::$bindingHandler)) {
            throw new FulfillmentContainerException('Fulfillment container binding handler was not configured');
        }

        return self::$bindingHandler;
    }

    public static function getSyncHandler(): FulfillmentSyncHandlerInterface
    {
        if (!isset(self::$syncHandler)) {
            throw new FulfillmentContainerException('Fulfillment container sync handler was not configured');
        }

        return self::$syncHandler;
    }

    public static function getRemoveHandler(): FulfillmentRemoveHandlerInterface
    {
        if (!isset(self::$removeHandler)) {
            throw new FulfillmentContainerException('Fulfillment container sync handler was not configured');
        }

        return self::$removeHandler;
    }

}