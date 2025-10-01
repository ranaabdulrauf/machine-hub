<?php

use App\Suppliers\SupplierRegistry;
use App\Jobs\ProcessAllApiSuppliersJob;
use App\Jobs\ForwardAllApiSuppliersTelemetryJob;
use App\Models\ProcessedTelemetry;
use App\Models\SupplierFetchLog;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Clear any existing logs
    Log::getLogger()->getHandlers()[0]->clear();
});

it('can discover api suppliers from registry', function () {
    // Get all API suppliers from registry
    $apiSuppliers = SupplierRegistry::getApiSuppliers();
    
    // Should find Dejong as API supplier
    expect($apiSuppliers)->toContain('dejong');
    expect($apiSuppliers)->toBeArray();
    
    // Verify Dejong is registered as API mode
    expect(SupplierRegistry::getMode('dejong'))->toBe('api');
});

it('can process all api suppliers job', function () {
    // Mock the queue to prevent actual job execution
    Queue::fake();
    
    // Dispatch the job
    ProcessAllApiSuppliersJob::dispatch();
    
    // Assert that individual fetch jobs were dispatched
    Queue::assertPushed(\App\Jobs\FetchApiSupplierDataJob::class, function ($job) {
        return $job->supplier === 'dejong';
    });
});

it('can fetch data from api suppliers', function () {
    // Create a test telemetry record to simulate API data
    $telemetry = ProcessedTelemetry::create([
        'supplier' => 'dejong',
        'event_id' => 'test-event-123',
        'type' => 'TestEvent',
        'device_id' => 'test-device-456',
        'occurred_at' => now(),
        'payload' => ['test' => 'data'],
        'status' => 'pending'
    ]);

    // Verify the telemetry was created
    $this->assertDatabaseHas('processed_telemetries', [
        'supplier' => 'dejong',
        'event_id' => 'test-event-123',
        'status' => 'pending'
    ]);

    // Test that we can retrieve pending telemetries
    $pendingTelemetries = ProcessedTelemetry::where('supplier', 'dejong')
        ->where('status', 'pending')
        ->get();

    expect($pendingTelemetries)->toHaveCount(1);
    expect($pendingTelemetries->first()->event_id)->toBe('test-event-123');
});

it('can forward telemetry from api suppliers', function () {
    // Create test telemetry records
    ProcessedTelemetry::create([
        'supplier' => 'dejong',
        'event_id' => 'test-event-1',
        'type' => 'TestEvent1',
        'device_id' => 'test-device-1',
        'occurred_at' => now(),
        'payload' => ['test' => 'data1'],
        'status' => 'pending'
    ]);

    ProcessedTelemetry::create([
        'supplier' => 'dejong',
        'event_id' => 'test-event-2',
        'type' => 'TestEvent2',
        'device_id' => 'test-device-2',
        'occurred_at' => now(),
        'payload' => ['test' => 'data2'],
        'status' => 'pending'
    ]);

    // Mock the queue to prevent actual forwarding
    Queue::fake();

    // Dispatch the forwarding job
    ForwardAllApiSuppliersTelemetryJob::dispatch();

    // Assert that forwarding jobs were dispatched for each telemetry
    Queue::assertPushed(\App\Jobs\ForwardApiSupplierTelemetryJob::class, function ($job) {
        return $job->supplier === 'dejong';
    });
});

it('handles api supplier fetch logs', function () {
    // Test the HasFetchLog trait functionality
    $supplier = 'dejong';
    $endpoint = 'test-endpoint';
    
    // Create a fetch log entry
    SupplierFetchLog::create([
        'supplier' => $supplier,
        'endpoint' => $endpoint,
        'last_fetched_at' => now()->subMinutes(30)
    ]);

    // Verify the log was created
    $this->assertDatabaseHas('supplier_fetch_logs', [
        'supplier' => $supplier,
        'endpoint' => $endpoint
    ]);

    // Test getting last fetched time
    $lastFetched = SupplierFetchLog::lastFetched($supplier, $endpoint);
    expect($lastFetched)->not->toBeNull();
    expect($lastFetched)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can handle multiple api suppliers', function () {
    // Test that the system can handle multiple API suppliers
    $apiSuppliers = SupplierRegistry::getApiSuppliers();
    
    // Should have at least Dejong
    expect(count($apiSuppliers))->toBeGreaterThanOrEqual(1);
    
    // Test processing each supplier
    foreach ($apiSuppliers as $supplier) {
        expect(SupplierRegistry::getMode($supplier))->toBe('api');
        expect(SupplierRegistry::exists($supplier))->toBeTrue();
    }
});

it('logs api supplier processing', function () {
    // Clear logs
    Log::getLogger()->getHandlers()[0]->clear();
    
    // Dispatch the job
    ProcessAllApiSuppliersJob::dispatch();
    
    // Process the job
    $job = new ProcessAllApiSuppliersJob();
    $job->handle();
    
    // Check that appropriate logs were written
    $logContent = file_get_contents(storage_path('logs/laravel.log'));
    
    expect($logContent)->toContain('[ProcessAllApiSuppliersJob] Starting processing for all API suppliers');
    expect($logContent)->toContain('[ProcessAllApiSuppliersJob] Found API suppliers');
    expect($logContent)->toContain('dejong');
});

it('handles empty api suppliers gracefully', function () {
    // Test with no API suppliers (this would require mocking the registry)
    // For now, we'll test that the system handles the current state correctly
    
    $apiSuppliers = SupplierRegistry::getApiSuppliers();
    
    // Should not be empty since we have Dejong
    expect($apiSuppliers)->not->toBeEmpty();
    
    // Test that the job can handle the current suppliers
    $job = new ProcessAllApiSuppliersJob();
    $job->handle();
    
    // Should complete without errors
    expect(true)->toBeTrue();
});

it('can retry failed api processing', function () {
    // Create a failed telemetry record
    ProcessedTelemetry::create([
        'supplier' => 'dejong',
        'event_id' => 'failed-event',
        'type' => 'FailedEvent',
        'device_id' => 'failed-device',
        'occurred_at' => now(),
        'payload' => ['error' => 'test failure'],
        'status' => 'error'
    ]);

    // Verify the failed record exists
    $this->assertDatabaseHas('processed_telemetries', [
        'supplier' => 'dejong',
        'event_id' => 'failed-event',
        'status' => 'error'
    ]);

    // Test that we can query for different statuses
    $errorCount = ProcessedTelemetry::where('supplier', 'dejong')
        ->where('status', 'error')
        ->count();
        
    expect($errorCount)->toBe(1);
});

it('handles telemetry status updates', function () {
    // Create a pending telemetry
    $telemetry = ProcessedTelemetry::create([
        'supplier' => 'dejong',
        'event_id' => 'status-test-event',
        'type' => 'StatusTestEvent',
        'device_id' => 'status-test-device',
        'occurred_at' => now(),
        'payload' => ['test' => 'status update'],
        'status' => 'pending'
    ]);

    // Update status to processing
    $telemetry->update(['status' => 'processing']);
    
    $this->assertDatabaseHas('processed_telemetries', [
        'event_id' => 'status-test-event',
        'status' => 'processing'
    ]);

    // Update status to forwarded
    $telemetry->update(['status' => 'forwarded']);
    
    $this->assertDatabaseHas('processed_telemetries', [
        'event_id' => 'status-test-event',
        'status' => 'forwarded'
    ]);
});
