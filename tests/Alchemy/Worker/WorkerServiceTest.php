<?php

namespace Alchemy\WorkerPlugin\Tests\Worker;

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
        $app = $this->prophesize('Alchemy\Phrasea\Application');

        $exportMailWorker = new ExportMailWorker($app->reveal());
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $exportMailWorker);


        $subdefCreationWorker = new SubdefCreationWorker($app->reveal());
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $subdefCreationWorker);


        $writeLogsWorker = new WriteLogsWorker($app->reveal());
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $writeLogsWorker);


        $writemetadatasWorker = new WriteMetadatasWorker($app->reveal());
        $this->assertInstanceOf('Alchemy\\WorkerPlugin\\Worker\\WorkerInterface', $writemetadatasWorker);
    }
}
