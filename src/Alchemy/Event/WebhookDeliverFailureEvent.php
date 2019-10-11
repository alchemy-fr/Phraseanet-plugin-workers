<?php

namespace  Alchemy\WorkerPlugin\Event;

use Symfony\Component\EventDispatcher\Event as SfEvent;

class WebhookDeliverFailureEvent extends SfEvent
{
    private $webhookEventId;
    private $workerMessage;

    public function __construct($webhookEventId, $workerMessage)
    {
        $this->webhookEventId       = $webhookEventId;
        $this->workerMessage        = $workerMessage;
    }

    public function getWebhookEventId()
    {
        return $this->webhookEventId;
    }

    public function getWorkerMessage()
    {
        return $this->workerMessage;
    }
}
