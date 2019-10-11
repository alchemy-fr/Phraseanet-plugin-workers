<?php

namespace  Alchemy\WorkerPlugin\Event;

use Symfony\Component\EventDispatcher\Event as SfEvent;

class AssetsCreationFailureEvent extends SfEvent
{
    private $payload;
    private $workerMessage;

    public function __construct($payload, $workerMessage)
    {
        $this->payload          = $payload;
        $this->workerMessage    = $workerMessage;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function getWorkerMessage()
    {
        return $this->workerMessage;
    }
}
