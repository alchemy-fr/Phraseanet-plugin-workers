<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\WorkerPlugin\Event\WebhookDeliverFailureEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WebhookSubscriber implements EventSubscriberInterface
{
    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(MessagePublisher $messagePublisher)
    {
        $this->messagePublisher = $messagePublisher;
    }

    public function onWebhookDeliverFailure(WebhookDeliverFailureEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::WEBHOOK_TYPE,
            'payload' => [
                'id' => $event->getWebhookEventId()
            ]
        ];

        $retryCount = 1;
        $this->messagePublisher->publishMessage(
            $payload,
            MessagePublisher::RETRY_WEBHOOK_QUEUE,
            $retryCount,
            $event->getWorkerMessage()
        );
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerPluginEvents::WEBHOOK_DELIVER_FAILURE => 'onWebhookDeliverFailure',
        ];
    }
}
