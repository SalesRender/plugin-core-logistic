<?php

namespace SalesRender\Helpers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use SalesRender\Plugin\Core\Logistic\Services\LogisticStatusesResolverService;

abstract class LogisticTestCase extends TestCase
{
    /**
     * @param $name
     * @return ReflectionMethod
     * @throws ReflectionException
     */
    public static function getMethod($name): ReflectionMethod
    {
        $class = new ReflectionClass(LogisticStatusesResolverService::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}