<?php

namespace App\MachineHub\Core\Services;

use App\MachineHub\Core\Services\HubForwarder;
use App\MachineHub\Core\Contracts\SupplierAdapter;

class EventProcessor
{
    protected SupplierAdapter $adapter;
    protected HubForwarder $forwarder;

    public function __construct(SupplierAdapter $adapter, HubForwarder $forwarder)
    {
        $this->adapter   = $adapter;
        $this->forwarder = $forwarder;
    }

    public function process(array $events): void
    {
        foreach ($events as $event) {
            $dto = $this->adapter->handleEvent($event);
            if ($dto) {
                $this->forwarder->send($dto, $this->adapter->name());
            }
        }
    }
}
