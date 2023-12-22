<?php
/**
 * Created for plugin-core-logistic
 * Date: 22.12.2023
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping;

use SalesRender\Plugin\Core\Actions\SpecialRequestAction;

abstract class RemoveOrdersAction extends SpecialRequestAction
{
    final public function getName(): string
    {
        return 'removeOrders';
    }
}