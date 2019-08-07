<?php

namespace Alchemy\WorkerPlugin\Tests\Queue;

use Alchemy\Phrasea\Application;
use Alchemy\WorkerPlugin\Queue\MessageHandler;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Alchemy\WorkerPlugin\Worker\WorkerInvoker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPTimeoutException;

class MessageHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConsume()
    {
        $app = new Application(Application::ENV_TEST);

        $app['alchemy_service.message.publisher'] = $this->prophesize('Alchemy\WorkerPlugin\Queue\MessagePublisher');

        $channel = $this->getMockBuilder(AMQPChannel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $channel->expects($this->atLeastOnce())
            ->method('basic_consume');

        $workerInvoker = $this->getMockBuilder(WorkerInvoker::class)
            ->disableOriginalConstructor()
            ->getMock();

        $sut = new MessageHandler($app['alchemy_service.message.publisher']->reveal());

        $sut->consume($channel, $workerInvoker, [MessagePublisher::METADATAS_QUEUE], true, true);

    }

    public function testChannelThrowException()
    {
        $app = new Application(Application::ENV_TEST);

        $app['alchemy_service.message.publisher'] = $this->prophesize('Alchemy\WorkerPlugin\Queue\MessagePublisher');

        $channel = $this->getMockBuilder(AMQPChannel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $channel->expects($this->atLeastOnce())
            ->method('basic_consume')
            ->will($this->throwException(new AMQPTimeoutException()))
        ;

        $workerInvoker = $this->getMockBuilder(WorkerInvoker::class)
            ->disableOriginalConstructor()
            ->getMock();

        $sut = new MessageHandler($app['alchemy_service.message.publisher']->reveal());

        try {
            $sut->consume($channel, $workerInvoker, [MessagePublisher::METADATAS_QUEUE], true, true);
            $this->fail('Should have raised an exception');
        } catch (AMQPTimeoutException $e) {

        }
    }
}
