<?php
/**
 * Created for plugin-core-logistic
 * Date: 10.02.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Batch\Batch;
use SalesRender\Plugin\Components\Batch\BatchHandlerInterface;
use SalesRender\Plugin\Components\Db\Helpers\UuidHelper;
use SalesRender\Plugin\Components\Guzzle\Guzzle;
use SalesRender\Plugin\Components\Logistic\Components\ShippingAttachment;
use SalesRender\Plugin\Core\Logistic\Components\Traits\BatchLockTrait;
use XAKEPEHOK\Path\Path;

abstract class BatchShippingHandler implements BatchHandlerInterface
{
    use BatchLockTrait;

    public function __construct()
    {
        $this->lockId = UuidHelper::getUuid();
    }

    protected function createShipping(Batch $batch, $removeOnCancelFields = []): int
    {
        $batch->getApiClient()::$lockId = $this->lockId;

        $token = $batch->getToken()->getInputToken();
        $uri = (new Path($token->getClaim('iss')))
            ->down('companies')
            ->down($token->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping');

        $response = Guzzle::getInstance()->post(
            (string)$uri,
            [
                'headers' => [
                    'X-PLUGIN-TOKEN' => (string)$batch->getToken()->getOutputToken(),
                ],
                'json' => [
                    'lockId' => $this->lockId,
                    'removeOnCancelFields' => $removeOnCancelFields,
                ],
            ],
        );

        if ($response->getStatusCode() !== 201) {
            throw new RuntimeException('Invalid response code', 100);
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['shippingId'])) {
            throw new RuntimeException('Invalid response', 200);
        }

        return $data['shippingId'];
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @param array $orders
     * @return Response|ResponseInterface
     * @throws GuzzleException
     */
    protected function addOrders(Batch $batch, string $shippingId, array $orders): ResponseInterface
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId)
            ->down('orders');

        return Registration::find()->makeSpecialRequest(
            'PATCH',
            $uri,
            [
                'shippingId' => $shippingId,
                'orders' => $orders,
                'lockId' => $this->lockId,
            ],
            60 * 10
        );
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @param int $ordersCount
     * @return Response|ResponseInterface
     * @throws GuzzleException
     */
    protected function markAsExported(Batch $batch, string $shippingId, int $ordersCount): ResponseInterface
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId)
            ->down('status/exported');

        return Registration::find()->makeSpecialRequest(
            'POST',
            $uri,
            [
                'shippingId' => $shippingId,
                'orders' => $ordersCount,
                'lockId' => $this->lockId,
            ],
            60 * 10
        );
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @return Response|ResponseInterface
     * @throws GuzzleException
     */
    protected function markAsFailed(Batch $batch, string $shippingId): ResponseInterface
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId)
            ->down('status/failed');

        return Registration::find()->makeSpecialRequest(
            'POST',
            $uri,
            [
                'shippingId' => $shippingId,
                'lockId' => $this->lockId,
            ],
            60 * 10
        );
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @param ShippingAttachment ...$shippingAttachment
     * @return ResponseInterface
     * @throws GuzzleException
     */
    protected function addShippingAttachments(Batch $batch, string $shippingId, ShippingAttachment ...$shippingAttachment): ResponseInterface
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId)
            ->down('attachments/add');

        return Registration::find()->makeSpecialRequest(
            'PATCH',
            $uri,
            [
                'shippingId' => $shippingId,
                'lockId' => $this->lockId,
                'attachments' => $shippingAttachment,
            ],
            60 * 10
        );
    }

}