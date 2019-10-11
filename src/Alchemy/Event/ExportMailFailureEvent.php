<?php

namespace  Alchemy\WorkerPlugin\Event;

use Symfony\Component\EventDispatcher\Event as SfEvent;

class ExportMailFailureEvent extends SfEvent
{
    private $emitterUserId;
    private $tokenValue;
    private $destinationMails;
    private $params;
    private $workerMessage;

    public function __construct($emitterUserId, $tokenValue, $destinationMails, $params, $workerMessage = '')
    {
        $this->emitterUserId    = $emitterUserId;
        $this->tokenValue       = $tokenValue;
        $this->destinationMails = $destinationMails;
        $this->params           = $params;
        $this->workerMessage    = $workerMessage;
    }

    public function getEmitterUserId()
    {
        return $this->emitterUserId;
    }

    public function getTokenValue()
    {
        return $this->tokenValue;
    }

    public function getDestinationMails()
    {
        return $this->destinationMails;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getWorkerMessage()
    {
        return $this->workerMessage;
    }
}
