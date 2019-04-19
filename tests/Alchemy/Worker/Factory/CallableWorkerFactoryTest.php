<?php

namespace Alchemy\WorkerPlugin\Tests\Factory\Resolver;

use Alchemy\WorkerPlugin\Worker\Factory\CallableWorkerFactory;

class CallableWorkerFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testClassImplements()
    {
        $sut = new CallableWorkerFactory(function () {});

        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\Factory\\WorkerFactoryInterface', $sut);
    }
}
