<?php
/**
 * Created for plugin-core-logistic
 * Date: 18.01.2024
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Fulfillment;

use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Core\Logistic\Components\Binding\Binding;

interface FulfillmentBindingHandlerInterface
{

    public function __invoke(Settings $settings): Binding;

}