<?php

namespace Alchemy\WorkerPlugin\Tests\Subscriber;

use Alchemy\Phrasea\Application;
use Alchemy\WorkerPlugin\Subscriber\ExportSubscriber;
use Alchemy\WorkerPlugin\Subscriber\RecordSubscriber;

/**
 * @covers Alchemy\WorkerPlugin\Subscriber\ExportSubscriber
 *  @covers Alchemy\WorkerPlugin\Subscriber\RecordSubscriber
 */
class SubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testCallsImplements()
    {
        $app = new Application(Application::ENV_TEST);
        $app['alchemy_service.message.publisher'] = $this->prophesize('Alchemy\WorkerPlugin\Queue\MessagePublisher');

        $sexportSubscriber = new ExportSubscriber($app['alchemy_service.message.publisher']->reveal());
        $this->assertInstanceOf('Symfony\\Component\\EventDispatcher\\EventSubscriberInterface', $sexportSubscriber);

        $recordSubscriber = new ExportSubscriber($app['alchemy_service.message.publisher']->reveal());
        $this->assertInstanceOf('Symfony\\Component\\EventDispatcher\\EventSubscriberInterface', $recordSubscriber);
    }

    public function testIfPublisheMessageOnSubscribeEvent()
    {
        $app = new Application(Application::ENV_TEST);
        $app['alchemy_service.message.publisher'] = $this->getMockBuilder('Alchemy\WorkerPlugin\Queue\MessagePublisher')
            ->disableOriginalConstructor()
            ->getMock();

        $app['alchemy_service.type_based_worker_resolver'] = $this->getMockBuilder('Alchemy\WorkerPlugin\Worker\Resolver\TypeBasedWorkerResolver')
            ->disableOriginalConstructor()
            ->getMock();

        $app['alchemy_service.message.publisher']->expects($this->atLeastOnce())->method('publishMessage');

        $event = $this->prophesize('Alchemy\Phrasea\Core\Event\ExportMailEvent');
        $sut = new ExportSubscriber($app['alchemy_service.message.publisher']);
        $sut->onExportMailCreate($event->reveal());


        $record = $this->prophesize('Alchemy\Phrasea\Model\RecordInterface');

        $event = $this->prophesize('Alchemy\Phrasea\Core\Event\Record\RecordEvent');
        $event->getRecord()->willReturn($record->reveal());
        $sut = new RecordSubscriber($app);
        $sut->setApplicationBox($app['phraseanet.appbox']);

        $sut->onRecordCreated($event->reveal());

        $event = $this->prophesize('Alchemy\Phrasea\Core\Event\Record\SubdefinitionCreateEvent');
        $event->getRecord()->willReturn($record->reveal());
        $event->isNewRecord()->willReturn(true);
        $sut->onSubdefinitionCreate($event->reveal());
    }
}
