<?php
/**
 * Created for plugin-core-logistic
 * Date: 18.01.2024
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Actions\Fulfillment;

use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\ApiClient\ApiClient;
use SalesRender\Plugin\Components\ApiClient\ApiFilterSortPaginate;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Components\SpecialRequest;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestTask;
use SalesRender\Plugin\Core\Actions\SpecialRequestAction;
use SalesRender\Plugin\Core\Logistic\Components\Actions\Fulfillment\Exception\FulfillmentSyncException;
use SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentContainer;
use SalesRender\Plugin\Core\Logistic\Components\OrderFetcherIterator;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use XAKEPEHOK\Path\Path;

class SyncAction extends SpecialRequestAction
{

    protected function handle(array $body, ServerRequest $request, Response $response, array $args): Response
    {
        $id = $body['id'];
        $orderId = $body['orderId'];

        $apiClient = new ApiClient(
            $body['endpoint'],
            $body['token']
        );

        $registration = Registration::find();
        if ($registration === null) {
            throw new FulfillmentSyncException('Failed to sync order. Plugin is not registered.');
        }

        $handler = FulfillmentContainer::getHandler();

        $iterator = new OrderFetcherIterator(
            ['orders' => $handler->getOrderFields()],
            $apiClient,
            new ApiFilterSortPaginate([
                'filters' => [
                    'include' => [
                        'ids' => [$orderId]
                    ]
                ]
            ], null, null),
            true,
            1
        );

        foreach ($iterator as $orderData) {
            $uri = (new Path($registration->getClusterUri()))
                ->down('companies')
                ->down(Connector::getReference()->getCompanyId())
                ->down('CRM/plugin/logistic/fulfillment/sync')
            ;

            $jwt = $registration->getSpecialRequestToken([
                'id' => $id,
                'orderId' => $orderId,
                'error' => $handler->handle($orderData)
            ], 24 * 60 * 60);

            $request = new SpecialRequest(
                'PUT',
                $uri,
                (string)$jwt,
                time() + 30 * 60,
                202,
            );
            $task = new SpecialRequestTask($request);
            $task->save();

            return $response->withStatus(202);
        }

        return $response->withStatus(400);
    }

    public function getName(): string
    {
        return 'ffSync';
    }
}