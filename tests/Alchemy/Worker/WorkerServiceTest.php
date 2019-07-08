<?php

namespace Alchemy\WorkerPlugin\Tests\Worker;

use Alchemy\Phrasea\Application;
use Alchemy\WorkerPlugin\Worker\AssetsIngestWorker;
use Alchemy\WorkerPlugin\Worker\CreateRecordWorker;
use Alchemy\WorkerPlugin\Worker\ExportMailWorker;
use Alchemy\WorkerPlugin\Worker\SubdefCreationWorker;
use Alchemy\WorkerPlugin\Worker\WriteLogsWorker;
use Alchemy\WorkerPlugin\Worker\WriteMetadatasWorker;

/**
 * @covers Alchemy\WorkerPlugin\Worker\ExportMailWorker
 * @covers Alchemy\WorkerPlugin\Worker\SubdefCreationWorker
 * @covers Alchemy\WorkerPlugin\Worker\WriteLogsWorker
 * @covers Alchemy\WorkerPlugin\Worker\WriteMetadatasWorker
 */
class WorkerServiceTest extends \PHPUnit_Framework_TestCase
{
    public function testImplementationClass()
    {
        $app = new Application(Application::ENV_TEST);

        $exportMailWorker = new ExportMailWorker($app);
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $exportMailWorker);

        $app['subdef.generator'] = $this->prophesize('Alchemy\Phrasea\Media\SubdefGenerator')->reveal();
        $app['alchemy_service.message.publisher'] = $this->prophesize('Alchemy\WorkerPlugin\Queue\MessagePublisher')->reveal();
        $app['alchemy_service.logger'] = $this->prophesize("Monolog\Logger")->reveal();
        $app['dispatcher'] = $this->prophesize('Symfony\Component\EventDispatcher\EventDispatcherInterface')->reveal();
        $writer = $this->prophesize('PHPExiftool\Writer')->reveal();

        $subdefCreationWorker = new SubdefCreationWorker(
            $app['subdef.generator'],
            $app['alchemy_service.message.publisher'],
            $app['alchemy_service.logger'],
            $app['dispatcher']
            );
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $subdefCreationWorker);


        $writeLogsWorker = new WriteLogsWorker($app['alchemy_service.logger']);
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $writeLogsWorker);


        $writemetadatasWorker = new WriteMetadatasWorker($writer, $app['alchemy_service.logger'], $app['alchemy_service.message.publisher']);
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $writemetadatasWorker);

        $assetsWorker = new AssetsIngestWorker($app);
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $assetsWorker);

        $createRecordWorker = new CreateRecordWorker($app);
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $createRecordWorker);
    }
}
