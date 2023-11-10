<?php
/**
 * Created for plugin-core-logistic
 * Date: 17.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Waybill\Response;


use JsonSerializable;
use SalesRender\Components\Address\Address;

class WaybillAddress implements JsonSerializable
{

    public string $field;

    public Address $address;

    public function __construct(string $field, Address $address)
    {
        $this->field = $field;
        $this->address = $address;
    }

    public function jsonSerialize(): array
    {
        return [
            'field' => $this->field,
            'value' => $this->address,
        ];
    }
}