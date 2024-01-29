<?php

namespace SalesRender\Plugin\Core\Logistic\Components\Traits;

use Adbar\Dot;
use SalesRender\Plugin\Components\Batch\Batch;

trait BatchLockTrait
{
    protected string $lockId;

    protected function lockOrder(int $timeout, int $orderId, Batch $batch): bool
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $client = $batch->getApiClient();

        $query = '
            mutation($id: ID!, $timeout: Int!) {
              lockMutation {
                lockEntity(input: { entity: { entity: Order, id: $id }, timeout: $timeout })
              }
            }
        ';

        $response = new Dot($client->query($query, [
            'id' => $orderId,
            'timeout' => $timeout,
        ])->getData());

        return $response->get('lockMutation.lockEntity', false);
    }
}