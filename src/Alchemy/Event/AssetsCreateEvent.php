<?php

namespace  Alchemy\WorkerPlugin\Event;

use Symfony\Component\EventDispatcher\Event as SfEvent;

class AssetsCreateEvent extends SfEvent
{
    private $data;

    public function __construct($data)
    {
        $this->data     = $data;
    }

    public function getData()
    {
        return $this->data;
    }

}
