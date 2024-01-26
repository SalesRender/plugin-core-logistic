<?php

namespace SalesRender\Plugin\Core\Logistic\Components;

use Psr\Http\Message\ResponseInterface;
use SalesRender\Plugin\Components\Batch\Batch;
use SalesRender\Plugin\Components\Batch\BatchHandlerInterface;
use SalesRender\Plugin\Components\Db\Helpers\UuidHelper;
use SalesRender\Plugin\Components\Guzzle\Guzzle;
use SalesRender\Plugin\Components\Logistic\Waybill\Waybill;
use SalesRender\Plugin\Core\Logistic\Components\Traits\BatchLockTrait;
use XAKEPEHOK\Path\Path;

abstract class BatchFulfillmentHandler implements BatchHandlerInterface
{
    use BatchLockTrait;

    public function __construct()
    {
        $this->lockId = UuidHelper::getUuid();
    }

    public function updateLogistic(Batch $batch, string $orderId, Waybill $waybill): ResponseInterface
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/fulfillment');

        return Guzzle::getInstance()->post(
            (string)$uri,
            [
                'headers' => [
                    'X-PLUGIN-TOKEN' => (string)$batch->getToken()->getOutputToken(),
                ],
                'json' => [
                    'lockId' => $this->lockId,
                    'orderId' => $orderId,
                    'waybill' => $waybill,
                ],
            ],
        );
    }
}