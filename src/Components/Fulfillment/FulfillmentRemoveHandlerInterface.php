<?php

namespace SalesRender\Plugin\Core\Logistic\Components\Fulfillment;

interface FulfillmentRemoveHandlerInterface
{
    /**
     * @return string|null should return null for success remove or human-readable error message on failure
     */
    public function handle(string $orderId): ?string;

}