<?php

namespace App\MachineHub\Suppliers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\MachineHub\Core\DTO\TelemetryDTO;
use App\MachineHub\Core\Traits\HasFetchLog;
use App\Models\ProcessedTelemetry;

class DejongAdapter extends AbstractSupplierAdapter
{
    use HasFetchLog;

    public function name(): string
    {
        return 'dejong';
    }

    public function mode(): string
    {
        return 'api';
    }

    public function verify(?Request $request): JsonResponse|bool
    {
        return true;
    }

    /**
     * Handle API polling: fetch new data, store it, and mark as fetched.
     */
    public function handleApi(): void
    {
        [$start, $end] = $this->getFetchRange($this->name(), 'consumptions');

        $endpoints   = ['consumptions', 'events'];
        $telemetries = [];

        foreach ($endpoints as $endpoint) {
            $method = "handle" . ucfirst($endpoint);
            if (method_exists($this, $method)) {
                $telemetries = array_merge($telemetries, $this->{$method}($start, $end));
            }
        }

        foreach ($telemetries as $dto) {
            ProcessedTelemetry::updateOrCreate(
                ['supplier' => $this->name(), 'event_id' => $dto->eventId],
                [
                    'type'        => $dto->type,
                    'occurred_at' => $dto->occurredAt,
                    'payload'     => $dto->payload,
                    'status'      => 'pending',
                ]
            );
        }

        $this->markFetched($this->name(), 'consumptions', $end);
    }

    protected function handleConsumptions(Carbon $start, Carbon $end): array
    {
        return $this->fetchPaginated('/consumptions', $start, $end, function ($item) {
            return new TelemetryDTO(
                type: 'Consumption',
                eventId: (string) ($item['id'] ?? uniqid()),
                deviceId: $item['machine_id'] ?? null,
                occurredAt: $item['timestamp'] ?? null,
                payload: $item
            );
        });
    }

    protected function handleEvents(Carbon $start, Carbon $end): array
    {
        return $this->fetchPaginated('/events', $start, $end, function ($item) {
            return new TelemetryDTO(
                type: 'Event',
                eventId: (string) ($item['id'] ?? uniqid()),
                deviceId: $item['machine_id'] ?? null,
                occurredAt: $item['timestamp'] ?? null,
                payload: $item
            );
        });
    }

    /**
     * Generic paginated fetcher from supplier API.
     */
    protected function fetchPaginated(string $endpoint, Carbon $start, Carbon $end, callable $mapper): array
    {
        $results = [];
        $page    = 1;
        $hasNext = true;

        while ($hasNext) {
            $query = [
                'filter[start_date]' => $start->toISOString(),
                'filter[end_date]'   => $end->toISOString(),
                'page'               => $page,
                'limit'              => 5000,
                'sort'               => 'timestamp',
            ];

            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->config['api_key']}",
                    'Accept'        => 'application/json',
                ])->get("{$this->config['base_url']}{$endpoint}", $query);

                if ($response->failed()) {
                    Log::error("[DejongAdapter] API call failed", [
                        'endpoint' => $endpoint,
                        'status'   => $response->status(),
                        'body'     => $response->body(),
                    ]);
                    break;
                }

                $data = $response->json('data') ?? [];
                foreach ($data as $item) {
                    $results[] = $mapper($item);
                }

                $hasNext = !empty($response->json('next_page_url'));
                $page++;
            } catch (\Throwable $e) {
                Log::error("[DejongAdapter] Exception during API fetch", [
                    'endpoint' => $endpoint,
                    'error'    => $e->getMessage(),
                ]);
                break;
            }
        }

        return $results;
    }

    /**
     * Forward a DTO to our common webhook URL.
     */
    protected function forwardToWebhook(TelemetryDTO $dto): void
    {
        $url = config('machinehub.webhook_url'); // put your URL in config

        try {
            Http::post($url, [
                'supplier' => $this->name(),
                'event'    => $dto->toArray(),
            ]);
            Log::info("[DejongAdapter] Forwarded DTO to webhook", ['eventId' => $dto->eventId]);
        } catch (\Throwable $e) {
            Log::error("[DejongAdapter] Failed to forward DTO", [
                'eventId' => $dto->eventId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
