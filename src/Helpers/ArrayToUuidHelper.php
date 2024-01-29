<?php

namespace SalesRender\Plugin\Core\Logistic\Helpers;

class ArrayToUuidHelper
{
    public static function generate(array $data): string
    {
        ksort($data);
        $hash = md5(json_encode($data));
        return preg_replace(
            '~(\w{8})(\w{4})(\w{4})(\w{4})(\w{12})~',
            '$1-$2-$3-$4-$5',
            $hash
        );
    }

}