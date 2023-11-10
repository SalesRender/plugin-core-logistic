<?php

namespace SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping;

use SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\Exception\ShippingContainerException;

final class ShippingContainer
{
    private static ShippingCancelAction $action;

    public static function config(ShippingCancelAction $action): void
    {
        self::$action = $action;
    }

    /**
     * @return ShippingCancelAction
     * @throws ShippingContainerException
     */
    public static function getShippingCancelAction(): ShippingCancelAction
    {
        if (!isset(self::$action)) {
            throw new ShippingContainerException('Shipping cancel action was not configured', 100);
        }

        return self::$action;
    }
}