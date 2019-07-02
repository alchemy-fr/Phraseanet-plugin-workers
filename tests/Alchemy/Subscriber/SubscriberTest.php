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
        $app = $this->prophesize('Alchemy\Phrasea\Application');

        $sexportSubscriber = new ExportSubscriber($app->reveal());
        $this->assertInstanceOf('Symfony\\Component\\EventDispatcher\\EventSubscriberInterface', $sexportSubscriber);

        $recordSubscriber = new ExportSubscriber($app->reveal());
        $this->assertInstanceOf('Symfony\\Component\\EventDispatcher\\EventSubscriberInterface', $recordSubscriber);
    }

    public function testIfPublisheMessageOnSubscribeEvent()
    {
        $app = new Application(Application::ENV_TEST);
        $subdefRepository = $this->prophesize('Alchemy\Phrasea\Databox\Subdef\MediaSubdefRepository');

        $app['alchemy_service.message.publisher'] = $this->getMockBuilder('Alchemy\WorkerPlugin\Queue\MessagePublisher')
            ->disableOriginalConstructor()
            ->getMock();

        $app['provider.repo.media_subdef'] = $this->getMockBuilder('Alchemy\Phrasea\Databox\DataboxBoundRepositoryProvider')
            ->disableOriginalConstructor()
            ->getMock();


        $app['alchemy_service.message.publisher']->expects($this->atLeastOnce())->method('publishMessage');
        $app['provider.repo.media_subdef']->expects($this->any())
            ->method('getMediaSubdefRepository')
            ->will($this->returnValue($subdefRepository->reveal()));


        $event = $this->prophesize('Alchemy\Phrasea\Core\Event\ExportMailEvent');
        $sut = new ExportSubscriber($app);
        $sut->onCreateExportMail($event->reveal());


        $record = $this->prophesize('Alchemy\Phrasea\Model\RecordInterface');

        $event = $this->prophesize('Alchemy\Phrasea\Core\Event\Record\RecordEvent');
        $event->getRecord()->willReturn($record->reveal());
        $sut = new RecordSubscriber($app);
        $sut->onRecordCreated($event->reveal());

        $event = $this->prophesize('Alchemy\Phrasea\Core\Event\Record\SubDefinitionRebuildEvent');
        $event->getRecord()->willReturn($record->reveal());
        $event->stopPropagation()->willReturn();
        $sut->onBuildSubdefs($event->reveal());

        $event = $this->prophesize('Alchemy\Phrasea\Core\Event\Record\MetadataChangedEvent');
        $event->getRecord()->willReturn($record->reveal());
        $sut->onMetadataChange($event->reveal());
    }
}
