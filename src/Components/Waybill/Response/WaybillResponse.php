<?php
/**
 * Created for plugin-core-logistic
 * Date: 17.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Waybill\Response;


use SalesRender\Plugin\Components\Logistic\Logistic;

class WaybillResponse
{

    public Logistic $logistic;

    public ?WaybillAddress $address;

    public function __construct(Logistic $logistic, ?WaybillAddress $waybillAddress)
    {
        $this->logistic = $logistic;
        $this->address = $waybillAddress;
    }

}