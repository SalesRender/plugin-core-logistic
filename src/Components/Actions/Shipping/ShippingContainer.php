<?php

namespace SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping;

use SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\Exception\ShippingContainerException;

final class ShippingContainer
{
    private static ShippingCancelAction $shippingCancelAction;
    private static RemoveOrdersAction $removeOrdersAction;

    public static function config(ShippingCancelAction $shippingCancelAction, RemoveOrdersAction $removeOrdersAction): void
    {
        self::$shippingCancelAction = $shippingCancelAction;
        self::$removeOrdersAction = $removeOrdersAction;
    }

    /**
     * @return ShippingCancelAction
     * @throws ShippingContainerException
     */
    public static function getShippingCancelAction(): ShippingCancelAction
    {
        if (!isset(self::$shippingCancelAction)) {
            throw new ShippingContainerException('Shipping cancel action was not configured', 100);
        }

        return self::$shippingCancelAction;
    }

    /**
     * @return RemoveOrdersAction
     * @throws ShippingContainerException
     */
    public static function getRemoveOrdersAction(): RemoveOrdersAction
    {
        if (!isset(self::$removeOrdersAction)) {
            throw new ShippingContainerException('Remove orders action was not configured', 200);
        }

        return self::$removeOrdersAction;
    }
}