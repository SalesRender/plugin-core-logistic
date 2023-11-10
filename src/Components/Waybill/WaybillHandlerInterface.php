<?php
/**
 * Created for plugin-core-logistic
 * Date: 09.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Waybill;


use SalesRender\Plugin\Components\Form\Form;
use SalesRender\Plugin\Components\Form\FormData;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\Response\WaybillResponse;

interface WaybillHandlerInterface
{

    public function __invoke(Form $form, FormData $data): WaybillResponse;

}