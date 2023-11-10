<?php
/**
 * Created for plugin-core-logistic
 * Date: 09.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Waybill;


use SalesRender\Plugin\Components\Form\Form;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\Exception\WaybillContainerException;

final class WaybillContainer
{

    /** @var callable */
    private static $form;

    private static WaybillHandlerInterface $handler;

    public static function config(callable $form, WaybillHandlerInterface $handler): void
    {
        self::$form = $form;
        self::$handler = $handler;
    }

    /**
     * @return Form
     * @throws WaybillContainerException
     */
    public static function getForm(): Form
    {
        if (!isset(self::$form)) {
            throw new WaybillContainerException('Waybill form was not configured', 100);
        }

        return (self::$form)();
    }

    /**
     * @return WaybillHandlerInterface
     * @throws WaybillContainerException
     */
    public static function getHandler(): WaybillHandlerInterface
    {
        if (!isset(self::$handler)) {
            throw new WaybillContainerException('Waybill handler was not configured', 200);
        }

        return self::$handler;
    }

}