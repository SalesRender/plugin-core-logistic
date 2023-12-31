<?php
/**
 * Created for plugin-core-logistic
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Factories;


use SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\ShippingContainer;
use SalesRender\Plugin\Core\Logistic\Components\Actions\Track\TrackGetStatusesAction;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillContainer;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerAction;
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
            ->get('/protected/track/statuses/{trackNumber:[A-z\d\-_]{6,25}}', TrackGetStatusesAction::class)
            ->add($this->protected);

        $this
            ->addCors()
            ->addSpecialRequestAction(ShippingContainer::getShippingCancelAction())
            ->addSpecialRequestAction(ShippingContainer::getRemoveOrdersAction());

        return parent::build();
    }

}