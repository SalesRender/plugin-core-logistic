<?php

namespace SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping;

use SalesRender\Plugin\Core\Actions\SpecialRequestAction;

abstract class ShippingCancelAction extends SpecialRequestAction
{
    final public function getName(): string
    {
        return 'shippingCancel';
    }
}