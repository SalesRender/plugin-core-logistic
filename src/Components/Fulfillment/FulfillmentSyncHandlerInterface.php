<?php
/**
 * Created for plugin-core-logistic
 * Date: 18.01.2024
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Fulfillment;

interface FulfillmentSyncHandlerInterface
{

    public function getOrderFields(): array;

    /**
     * @param array $orderData
     * @return string|null should return null for success sync or human-readable error message on failure
     */
    public function handle(array $orderData): ?string;

}