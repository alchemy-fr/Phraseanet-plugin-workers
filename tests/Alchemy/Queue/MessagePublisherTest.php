<?php

namespace Alchemy\WorkerPlugin\Tests\Queue;

use Alchemy\Phrasea\Application;
use Alchemy\WorkerPlugin\Queue\AMQPConnection;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Message\AMQPMessage;

class MessagePublisherTest extends \PHPUnit_Framework_TestCase
{
    public function testMessageArePublishedInExchange()
    {
        /** @var AMQPChannel $channel */
        $channel = $this->prophesize(AMQPChannel::class);

        $channel->basic_publish( new AMQPMessage(json_encode(['mock-payload'])), AMQPConnection::ALCHEMY_EXCHANGE, 'mock-queue')
            ->willReturn();

        $app = new Application(Application::ENV_TEST);

        $app['alchemy_service.amqp.connection'] = $this->prophesize("Alchemy\WorkerPlugin\Queue\AMQPConnection");

        $app['alchemy_service.amqp.connection']->setQueue('mock-queue')->willReturn($channel->reveal());

        $app['alchemy_service.amqp.connection'] = $app['alchemy_service.amqp.connection']->reveal();

        $app['alchemy_service.logger'] = $this->prophesize("Monolog\Logger")->reveal();

        $sut = new MessagePublisher($app['alchemy_service.amqp.connection'], $app['alchemy_service.logger']);

        $this->assertTrue($sut->publishMessage(['mock-payload'], 'mock-queue'));
    }

    public function testMessageAreNotPublishedInExchange()
    {
        /** @var AMQPChannel $channel */
        $channel = $this->prophesize(AMQPChannel::class);

        $channel->basic_publish(new AMQPMessage(json_encode(['mock-payload'])), AMQPConnection::ALCHEMY_EXCHANGE, 'mock-queue')
            ->shouldBeCalled()
            ->willThrow(new AMQPConnectionClosedException());

        $app = new Application(Application::ENV_TEST);

        $app['alchemy_service.amqp.connection'] = $this->prophesize("Alchemy\WorkerPlugin\Queue\AMQPConnection");

        $app['alchemy_service.amqp.connection']->setQueue('mock-queue')->willReturn($channel->reveal());

        $app['alchemy_service.amqp.connection'] = $app['alchemy_service.amqp.connection']->reveal();

        $app['alchemy_service.logger'] = $this->prophesize("Monolog\Logger")->reveal();

        $sut = new MessagePublisher($app['alchemy_service.amqp.connection'], $app['alchemy_service.logger']);

        try {
            $sut->publishMessage(['mock-payload'], 'mock-queue');
            $this->fail('Should have raised an exception');
        } catch (AMQPConnectionClosedException $e) {

        }
    }
}
