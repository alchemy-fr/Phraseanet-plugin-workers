<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\Phrasea\Core\Event\ExportMailEvent;
use Alchemy\Phrasea\Core\PhraseaEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExportSubscriber implements EventSubscriberInterface
{
    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(MessagePublisher $messagePublisher)
    {
        $this->messagePublisher = $messagePublisher;
    }

    public function onExportMailCreate(ExportMailEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::EXPORT_MAIL_TYPE,
            'payload' => [
                'emitterUserId'     => $event->getEmitterUserId(),
                'tokenValue'        => $event->getTokenValue(),
                'destinationMails'  => serialize($event->getDestinationMails()),
                'params'            => serialize($event->getParams())
            ]
        ];

        $this->messagePublisher->publishMessage($payload, MessagePublisher::EXPORT_QUEUE);
    }

    public static function getSubscribedEvents()
    {
        return [
            PhraseaEvents::EXPORT_MAIL_CREATE => 'onExportMailCreate'
        ];
    }
}
