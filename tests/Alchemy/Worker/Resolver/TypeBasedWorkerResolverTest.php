<?php

namespace Alchemy\WorkerPlugin\Tests\Worker\Resolver;

use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Alchemy\WorkerPlugin\Worker\Factory\CallableWorkerFactory;
use Alchemy\WorkerPlugin\Worker\Factory\WorkerFactoryInterface;
use Alchemy\WorkerPlugin\Worker\Resolver\TypeBasedWorkerResolver;
use Alchemy\WorkerPlugin\Worker\WorkerInterface;

class TypeBasedWorkerResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testClassImplements()
    {
        $sut = new TypeBasedWorkerResolver();

        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\Resolver\\WorkerResolverInterface', $sut);
    }

    public function testGetFactories()
    {
        $workerFactory = $this->getMockBuilder(WorkerFactoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $sut = new TypeBasedWorkerResolver();

        $sut->setFactory(MessagePublisher::SUBDEF_CREATION_TYPE, $workerFactory);

        $this->assertContainsOnlyInstancesOf(WorkerFactoryInterface::class, $sut->getFactories());
    }

    public function testGetWorkerSuccess()
    {
        $worker = $this->getMockBuilder(WorkerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $workerFactory = $this->getMockBuilder(CallableWorkerFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $workerFactory->method('createWorker')->will($this->returnValue($worker));

        $sut = new TypeBasedWorkerResolver();

        $sut->setFactory(MessagePublisher::SUBDEF_CREATION_TYPE, $workerFactory);


        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface',
            $sut->getWorker(MessagePublisher::SUBDEF_CREATION_TYPE, ['mock-message']));

    }

    public function testGetWorkerWrongTypeThrowException()
    {
        $worker = $this->getMockBuilder(WorkerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $workerFactory = $this->getMockBuilder(CallableWorkerFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $workerFactory->method('createWorker')->will($this->returnValue($worker));

        $sut = new TypeBasedWorkerResolver();

        $sut->setFactory(MessagePublisher::SUBDEF_CREATION_TYPE, $workerFactory);

        $this->expectException(\RuntimeException::class);

        $sut->getWorker(MessagePublisher::WRITE_METADATAs_TYPE, ['mock-message']);

    }
}
