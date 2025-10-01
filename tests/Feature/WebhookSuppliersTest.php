<?php

use App\Suppliers\SupplierRegistry;
use App\Http\Controllers\WMFController;
use App\Http\Controllers\FrankeController;
use App\Suppliers\WMFAdapter;
use App\Suppliers\FrankeAdapter;
use App\Jobs\ForwardTelemetryJob;
use App\DTOs\TelemetryDTO;
use App\Tenants\TenantForwarder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

beforeEach(function () {
    // Clear any existing logs
    Log::getLogger()->getHandlers()[0]->clear();
});

it('can discover webhook suppliers from registry', function () {
    // Get all webhook suppliers from registry
    $webhookSuppliers = SupplierRegistry::getWebhookSuppliers();

    // Should find WMF and Franke as webhook suppliers
    expect($webhookSuppliers)->toContain('wmf');
    expect($webhookSuppliers)->toContain('franke');
    expect($webhookSuppliers)->toBeArray();

    // Verify WMF is registered as webhook mode
    expect(SupplierRegistry::getMode('wmf'))->toBe('webhook');
    expect(SupplierRegistry::getMode('franke'))->toBe('webhook');
});

it('can handle wmf webhook requests', function () {
    // Mock the queue to prevent actual job execution
    Queue::fake();

    // Create test webhook data
    $webhookData = [
        [
            'id' => 'wmf-event-123',
            'device_id' => 'wmf-device-456',
            'timestamp' => now()->toISOString(),
            'eventType' => 'Dispensing',
            'data' => [
                'DeviceId' => 'wmf-device-456',
                'Beverage' => 'Coffee',
                'Volume' => 250
            ]
        ]
    ];

    // Make a POST request to WMF webhook endpoint
    $response = $this->postJson('/webhook/wmf/yellowbeared', $webhookData);

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the response contains expected data
    $response->assertJson([
        'message' => 'WMF webhook processed',
        'supplier' => 'wmf',
        'tenant' => 'yellowbeared'
    ]);

    // Assert that forwarding jobs were dispatched
    Queue::assertPushed(ForwardTelemetryJob::class, function ($job) {
        return $job->supplier === 'wmf' && $job->tenant === 'yellowbeared';
    });
});

it('can handle franke webhook requests', function () {
    // Mock the queue to prevent actual job execution
    Queue::fake();

    // Create test webhook data
    $webhookData = [
        [
            'id' => 'franke-event-789',
            'device_id' => 'franke-device-101',
            'timestamp' => now()->toISOString(),
            'eventType' => 'Maintenance',
            'data' => [
                'DeviceId' => 'franke-device-101',
                'Action' => 'Filter Change',
                'Status' => 'Completed'
            ]
        ]
    ];

    // Make a POST request to Franke webhook endpoint
    $response = $this->postJson('/webhook/franke/yellowrock', $webhookData);

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the response contains expected data
    $response->assertJson([
        'message' => 'Franke webhook processed',
        'supplier' => 'franke',
        'tenant' => 'yellowrock'
    ]);

    // Assert that forwarding jobs were dispatched
    Queue::assertPushed(ForwardTelemetryJob::class, function ($job) {
        return $job->supplier === 'franke' && $job->tenant === 'yellowrock';
    });
});

it('can process multiple webhook events', function () {
    // Mock the queue
    Queue::fake();

    // Create multiple webhook events
    $webhookData = [
        [
            'id' => 'event-1',
            'device_id' => 'device-1',
            'timestamp' => now()->toISOString(),
            'eventType' => 'Dispensing',
            'data' => ['test' => 'data1']
        ],
        [
            'id' => 'event-2',
            'device_id' => 'device-2',
            'timestamp' => now()->toISOString(),
            'eventType' => 'Error',
            'data' => ['test' => 'data2']
        ],
        [
            'id' => 'event-3',
            'device_id' => 'device-3',
            'timestamp' => now()->toISOString(),
            'eventType' => 'Maintenance',
            'data' => ['test' => 'data3']
        ]
    ];

    // Make a POST request with multiple events
    $response = $this->postJson('/webhook/wmf/yellowbeared', $webhookData);

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the response shows correct count
    $response->assertJson([
        'processed_count' => 3,
        'total_events' => 3
    ]);

    // Assert that forwarding jobs were dispatched for each event
    Queue::assertPushed(ForwardTelemetryJob::class, 3);
});

it('can create telemetry dto from webhook events', function () {
    // Test WMF event processing
    $wmfAdapter = new WMFAdapter();

    $event = [
        'id' => 'test-event-123',
        'device_id' => 'test-device-456',
        'timestamp' => now()->toISOString(),
        'eventType' => 'TestEvent',
        'data' => ['test' => 'data']
    ];

    $dto = $wmfAdapter->handleEvent($event);

    // Assert DTO was created
    expect($dto)->toBeInstanceOf(TelemetryDTO::class);
    expect($dto->type)->toBe('WMFEvent');
    expect($dto->eventId)->toBe('test-event-123');
    expect($dto->deviceId)->toBe('test-device-456');
});

it('handles webhook errors gracefully', function () {
    // Mock the queue
    Queue::fake();

    // Create invalid webhook data
    $invalidData = [
        'invalid' => 'data',
        'missing' => 'required_fields'
    ];

    // Make a POST request with invalid data
    $response = $this->postJson('/webhook/wmf/yellowbeared', $invalidData);

    // Should still return 200 but with errors
    $response->assertStatus(200);

    // Should show processed count of 0
    $response->assertJson([
        'processed_count' => 0,
        'total_events' => 1
    ]);
});

it('can handle different tenants', function () {
    // Mock the queue
    Queue::fake();

    $webhookData = [
        [
            'id' => 'tenant-test-event',
            'device_id' => 'tenant-test-device',
            'timestamp' => now()->toISOString(),
            'eventType' => 'TenantTest',
            'data' => ['test' => 'tenant data']
        ]
    ];

    // Test different tenants
    $tenants = ['yellowbeared', 'yellowrock', 'hermelin'];

    foreach ($tenants as $tenant) {
        $response = $this->postJson("/webhook/wmf/{$tenant}", $webhookData);

        $response->assertStatus(200);
        $response->assertJson([
            'tenant' => $tenant,
            'supplier' => 'wmf'
        ]);
    }
});

it('logs webhook processing', function () {
    // Clear logs
    Log::getLogger()->getHandlers()[0]->clear();

    // Mock the queue
    Queue::fake();

    $webhookData = [
        [
            'id' => 'log-test-event',
            'device_id' => 'log-test-device',
            'timestamp' => now()->toISOString(),
            'eventType' => 'LogTest',
            'data' => ['test' => 'log data']
        ]
    ];

    // Make a webhook request
    $this->postJson('/webhook/wmf/yellowbeared', $webhookData);

    // Check that appropriate logs were written
    $logContent = file_get_contents(storage_path('logs/laravel.log'));

    expect($logContent)->toContain('[WMFController] Webhook received');
    expect($logContent)->toContain('supplier');
    expect($logContent)->toContain('tenant');
});

it('handles empty webhook payloads', function () {
    // Mock the queue
    Queue::fake();

    // Test with empty payload
    $response = $this->postJson('/webhook/wmf/yellowbeared', []);

    $response->assertStatus(200);
    $response->assertJson([
        'processed_count' => 0,
        'total_events' => 0
    ]);
});

it('can handle webhook retries', function () {
    // Mock the queue
    Queue::fake();

    $webhookData = [
        [
            'id' => 'retry-test-event',
            'device_id' => 'retry-test-device',
            'timestamp' => now()->toISOString(),
            'eventType' => 'RetryTest',
            'data' => ['test' => 'retry data']
        ]
    ];

    // Make multiple requests (simulating retries)
    for ($i = 0; $i < 3; $i++) {
        $response = $this->postJson('/webhook/wmf/yellowbeared', $webhookData);
        $response->assertStatus(200);
    }

    // Should handle all requests successfully
    Queue::assertPushed(ForwardTelemetryJob::class, 3);
});

it('handles webhook forwarding jobs', function () {
    // Mock the tenant forwarder
    $this->mock(TenantForwarder::class, function ($mock) {
        $mock->shouldReceive('forwardToTenant')
            ->once()
            ->andReturn(true);
    });

    // Create a test DTO
    $dto = new TelemetryDTO(
        type: 'TestEvent',
        eventId: 'test-123',
        deviceId: 'device-456',
        occurredAt: now(),
        payload: ['test' => 'data']
    );

    // Dispatch a forwarding job
    ForwardTelemetryJob::dispatch('wmf', 'yellowbeared', $dto);

    // Process the job
    $job = new ForwardTelemetryJob('wmf', 'yellowbeared', $dto);
    $job->handle(app(TenantForwarder::class));

    // Should complete without errors
    expect(true)->toBeTrue();
});
