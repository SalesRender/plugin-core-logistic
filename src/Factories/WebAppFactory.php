<?php
/**
 * Created for plugin-core-logistic
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Factories;


use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Core\Logistic\Components\Actions\Fulfillment\SyncAction;
use SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\ShippingContainer;
use SalesRender\Plugin\Core\Logistic\Components\Actions\Track\TrackGetStatusesAction;
use SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentContainer;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillContainer;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerAction;
use SalesRender\Plugin\Core\Logistic\Helpers\LogisticHelper;
use Slim\App;

class WebAppFactory extends \SalesRender\Plugin\Core\Factories\WebAppFactory
{

    public function build(): App
    {
        $this
            ->addCors()
            ->addBatchActions()
            ->addForm(
                'waybill',
                fn(array $context) => WaybillContainer::getForm($context),
                new WaybillHandlerAction()
            );

        $this->app
            ->get('/protected/track/statuses/{trackNumber:[A-z\d\-_]{6,36}}', TrackGetStatusesAction::class)
            ->add($this->protected);

        if (LogisticHelper::isFulfillment()) {
            $this
                ->addCors()
                ->addSpecialRequestAction(new SyncAction());
        } else {
            $this
                ->addCors()
                ->addSpecialRequestAction(ShippingContainer::getShippingCancelAction())
                ->addSpecialRequestAction(ShippingContainer::getRemoveOrdersAction());
        }

        Settings::addOnSaveHandler(function (Settings $settings) {
            if (LogisticHelper::isFulfillment()) {
                $handler = FulfillmentContainer::getBindingHandler();
                $handler($settings)->sync();
            }
        }, 'ffSync');

        return parent::build();
    }

}