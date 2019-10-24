<?php

namespace  Alchemy\WorkerPlugin\Event;

use Symfony\Component\EventDispatcher\Event as SfEvent;

class WebhookDeliverFailureEvent extends SfEvent
{
    private $webhookEventId;
    private $workerMessage;
    private $count;
    private $uniqueUrl;

    public function __construct($webhookEventId, $workerMessage, $count = 2, $uniqueUrl = '')
    {
        $this->webhookEventId   = $webhookEventId;
        $this->workerMessage    = $workerMessage;
        $this->count            = $count;
        $this->uniqueUrl        = $uniqueUrl;
    }

    public function getWebhookEventId()
    {
        return $this->webhookEventId;
    }

    public function getWorkerMessage()
    {
        return $this->workerMessage;
    }

    public function getCount()
    {
        return $this->count;
    }

    public function getUniqueUrl()
    {
        return $this->uniqueUrl;
    }
}
