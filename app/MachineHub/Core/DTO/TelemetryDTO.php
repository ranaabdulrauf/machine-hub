<?php

namespace App\MachineHub\Core\DTO;

class TelemetryDTO
{
    public function __construct(
        public string $type,
        public string $eventId,
        public ?string $deviceId,
        public ?string $occurredAt,
        public array $payload
    ) {}

    public function toArray(): array
    {
        return [
            'type'       => $this->type,
            'eventId'    => $this->eventId,
            'deviceId'   => $this->deviceId,
            'occurredAt' => $this->occurredAt,
            'payload'    => $this->payload,
        ];
    }
}
