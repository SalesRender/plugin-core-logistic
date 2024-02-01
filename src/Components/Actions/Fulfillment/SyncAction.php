<?php
/**
 * Created for plugin-core-logistic
 * Date: 18.01.2024
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Actions\Fulfillment;

use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Access\Token\GraphqlInputToken;
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
        $registration = Registration::find();
        if ($registration === null) {
            throw new FulfillmentSyncException('Failed to sync order. Plugin is not registered.');
        }

        $id = $body['id'];
        $orderId = $body['orderId'];

        $apiClient = new ApiClient(
            $body['endpoint'],
            (new GraphqlInputToken($body['token']))->getOutputToken(),
        );

        $uri = (new Path($registration->getClusterUri()))
            ->down('companies')
            ->down(Connector::getReference()->getCompanyId())
            ->down('CRM/plugin/logistic/fulfillment/sync');

        $syncHandler = FulfillmentContainer::getSyncHandler();
        $removeHandler = FulfillmentContainer::getRemoveHandler();

        if ($body['token'] === null) {
            $this->sendSpecialRequest(
                $registration,
                $uri,
                $id,
                $orderId,
                $removeHandler->handle($orderId),
            );
            return $response->withStatus(202);
        }

        $iterator = new OrderFetcherIterator(
            ['orders' => $syncHandler->getGraphqlOrderFields()],
            $apiClient,
            new ApiFilterSortPaginate([
                'include' => [
                    'ids' => [$orderId]
                ]
            ], null, 1),
            true,
            1
        );

        foreach ($iterator as $graphqlOrder) {
            $this->sendSpecialRequest(
                $registration,
                $uri,
                $id,
                $orderId,
                $syncHandler->handle($graphqlOrder),
            );
            return $response->withStatus(202);
        }

        return $response->withStatus(400);
    }

    public function getName(): string
    {
        return 'ffSync';
    }

    private function sendSpecialRequest(Registration $registration, Path $uri, string $id, string $orderId, ?string $error): void
    {
        $jwt = $registration->getSpecialRequestToken([
            'id' => $id,
            'orderId' => $orderId,
            'error' => $error,
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
    }
}