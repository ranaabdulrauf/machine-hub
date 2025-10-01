<?php

use App\Suppliers\SupplierRegistry;
use App\Http\Controllers\WMFController;
use App\Http\Controllers\FrankeController;
use App\Suppliers\WMFAdapter;
use App\Suppliers\FrankeAdapter;
use App\Jobs\ForwardTelemetryJob;
use App\DTOs\TelemetryDTO;
use App\Tenants\TenantForwarder;
use App\Models\ProcessedTelemetry;
use App\Models\SupplierFetchLog;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

beforeEach(function () {
    // Clear database to avoid duplicate key errors
    ProcessedTelemetry::truncate();
    SupplierFetchLog::truncate();
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

    // Create test webhook data (this is what WMF sends to us)
    $webhookData = [
        [
            'id' => 'wmf-event-123',
            'timestamp' => now()->toISOString(),
            'eventType' => 'Dispensing',
            'data' => [
                'DeviceId' => 'wmf-device-456',
                'Beverage' => 'Coffee',
                'Volume' => 250
            ]
        ]
    ];

    // Make a POST request to our WMF webhook endpoint (incoming)
    $response = $this->postJson('/webhook/wmf/yellowbeared', $webhookData);

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the response contains expected data
    $response->assertJson([
        'message' => 'WMF webhook processed',
        'supplier' => 'wmf',
        'tenant' => 'yellowbeared'
    ]);

    // Assert that forwarding jobs were dispatched (these will forward to tenant's Dobby platform)
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
            'timestamp' => now()->toISOString(),
            'eventType' => 'Dispensing',
            'data' => ['test' => 'data1']
        ],
        [
            'id' => 'event-2',
            'timestamp' => now()->toISOString(),
            'eventType' => 'Error',
            'data' => ['test' => 'data2']
        ],
        [
            'id' => 'event-3',
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
        'timestamp' => now()->toISOString(),
        'eventType' => 'TestEvent',
        'data' => ['test' => 'data']
    ];

    $dto = $wmfAdapter->handleEvent($event);

    // Assert DTO was created
    expect($dto)->toBeInstanceOf(TelemetryDTO::class);
    expect($dto->type)->toBe('WMFEvent');
    expect($dto->eventId)->toBe('test-event-123');
    expect($dto->deviceId)->toBeNull(); // deviceId is not in the test data
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
        'total_events' => 0
    ]);
});

it('can handle different tenants', function () {
    // Mock the queue
    Queue::fake();

    $webhookData = [
        [
            'id' => 'tenant-test-event',
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
    // Test logging functionality

    // Mock the queue
    Queue::fake();

    $webhookData = [
        [
            'id' => 'log-test-event',
            'timestamp' => now()->toISOString(),
            'eventType' => 'LogTest',
            'data' => ['test' => 'log data']
        ]
    ];

    // Make a webhook request
    $this->postJson('/webhook/wmf/yellowbeared', $webhookData);

    // Check that appropriate logs were written
    $logContent = file_get_contents(storage_path('logs/laravel.log'));

    expect($logContent)->toContain('[WMFController] Processing webhook');
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
            ->twice()
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

it('handles wmf subscription validation', function () {
    // Create subscription validation event
    $validationData = [
        [
            'id' => 'validation-event-123',
            'eventType' => 'Microsoft.EventGrid.SubscriptionValidationEvent',
            'data' => [
                'validationCode' => 'test-validation-code-123'
            ]
        ]
    ];

    // Make a POST request with validation event
    $response = $this->postJson('/webhook/wmf/yellowbeared', $validationData);

    // Should return validation response
    $response->assertStatus(200);
    $response->assertJson([
        'validationResponse' => 'test-validation-code-123'
    ]);
});

it('handles wmf options request for abuse protection', function () {
    // Make an OPTIONS request for CloudEvents v1.0 abuse protection
    $response = $this->options('/webhook/wmf/yellowbeared', [], [
        'WebHook-Request-Origin' => 'eventemitter.example.com'
    ]);

    // Should return proper headers for abuse protection
    $response->assertStatus(200);
    $response->assertHeader('WebHook-Allowed-Origin', 'eventemitter.example.com');
    $response->assertHeader('WebHook-Allowed-Rate', '1000');
    $response->assertHeader('Allow', 'POST');
});

it('rejects options request without origin header', function () {
    // Make an OPTIONS request without required header
    $response = $this->options('/webhook/wmf/yellowbeared');

    // Should return error
    $response->assertStatus(400);
    $response->assertJson([
        'error' => 'Missing WebHook-Request-Origin header'
    ]);
});

it('handles azure event grid validation with headers', function () {
    // Create validation request with Azure Event Grid headers
    $response = $this->postJson('/webhook/wmf/yellowbeared', [
        [
            'id' => 'validation-event-123',
            'eventType' => 'Microsoft.EventGrid.SubscriptionValidationEvent',
            'data' => [
                'validationCode' => 'test-validation-code-123'
            ]
        ]
    ], [
        'aeg-event-type' => 'SubscriptionValidation',
        'aeg-subscription-name' => 'wmf-telemetry-subscription'
    ]);

    // Should return validation response
    $response->assertStatus(200);
    $response->assertJson([
        'validationResponse' => 'test-validation-code-123'
    ]);
});

it('verifies tenant forwarding urls use dynamic environment variables', function () {
    // Test that tenant forwarding URLs use environment variables
    $tenantConfig = config('machinehub.suppliers.wmf.tenants.yellowbeared');

    // Should have webhook_url configured (will be null in test env since env vars not set)
    expect($tenantConfig)->toHaveKey('webhook_url');
    expect($tenantConfig['webhook_url'])->toBeNull(); // No env var set in test

    // Test that we can get tenant config through TenantResolver
    $resolvedConfig = \App\Tenants\TenantResolver::getTenantConfig('wmf', 'yellowbeared');
    expect($resolvedConfig)->not->toBeNull();

    // Test that TenantForwarder can get configured tenants
    $forwarder = new TenantForwarder();
    $configuredTenants = $forwarder->getConfiguredTenants('wmf');

    expect($configuredTenants)->toContain('yellowbeared');
    expect($configuredTenants)->toContain('yellowrock');
    expect($configuredTenants)->toContain('hermelin');
});

it('logs warning when tenant webhook url is not configured', function () {
    // Test logging functionality

    // Create a test DTO
    $dto = new TelemetryDTO(
        type: 'TestEvent',
        eventId: 'test-123',
        deviceId: 'device-456',
        occurredAt: now(),
        payload: ['test' => 'data']
    );

    // Test forwarding to tenant without webhook URL configured
    $forwarder = new TenantForwarder();
    $result = $forwarder->forwardToTenant($dto, 'wmf', 'yellowbeared');

    // Should return false since no webhook URL is configured
    expect($result)->toBeFalse();

    // Check that warning was logged with payload
    $logContent = file_get_contents(storage_path('logs/laravel.log'));
    expect($logContent)->toContain('[TenantForwarder] No webhook URL configured for tenant - logging payload that would be sent');
    expect($logContent)->toContain('yellowbeared_WEBHOOK_URL environment variable');
    expect($logContent)->toContain('"payload"'); // Should contain the actual payload
});
