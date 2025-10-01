<?php

namespace App\Console\Commands;

use App\Suppliers\SupplierRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeAdapterCommand extends Command
{
    protected $signature = 'make:adapter {name} 
                            {--supplier= : Supplier name (defaults to adapter name)}
                            {--mode=webhook : Mode (webhook|api)}
                            {--controller= : Controller name (defaults to {Supplier}Controller)}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a new supplier adapter with optional controller';

    public function handle()
    {
        $adapterName = $this->argument('name');
        $supplierName = $this->option('supplier') ?: $this->extractSupplierName($adapterName);
        $mode = $this->option('mode');
        $controllerName = $this->option('controller') ?: $supplierName . 'Controller';
        $force = $this->option('force');

        // Validate mode
        if (!in_array($mode, ['webhook', 'api'])) {
            $this->error("Mode must be 'webhook' or 'api'");
            return 1;
        }

        // Check if supplier already exists
        if (SupplierRegistry::exists($supplierName)) {
            if (!$force) {
                $this->error("Supplier '{$supplierName}' already exists. Use --force to overwrite.");
                return 1;
            }
            $this->warn("Overwriting existing supplier '{$supplierName}'");
        }

        $this->info("Creating adapter for supplier: {$supplierName}");
        $this->info("Mode: {$mode}");
        $this->info("Adapter: {$adapterName}");
        $this->info("Controller: {$controllerName}");

        // Create adapter
        $this->createAdapter($adapterName, $supplierName, $mode, $force);

        // Create controller if needed
        if ($mode === 'webhook') {
            $this->createController($controllerName, $supplierName, $force);
            $this->updateRoutes($supplierName, $controllerName);
        }

        // Register supplier
        $adapterClass = "App\\Suppliers\\{$adapterName}";
        SupplierRegistry::register($supplierName, $adapterClass);

        // Update configuration
        $this->updateConfiguration($supplierName, $mode);

        $this->info("✅ Adapter created successfully!");
        
        if ($mode === 'webhook') {
            $this->info("✅ Controller created: {$controllerName}");
            $this->info("✅ Route added: /webhook/{$supplierName}/{tenant}");
        } else {
            $this->info("✅ API supplier - will be processed by scheduled jobs");
        }

        $this->info("✅ Supplier registered: {$supplierName}");
        $this->info("✅ Configuration updated");

        return 0;
    }

    protected function extractSupplierName(string $adapterName): string
    {
        // Remove "Adapter" suffix if present
        $name = Str::replaceLast('Adapter', '', $adapterName);
        
        // Convert to snake_case
        return Str::snake($name);
    }

    protected function createAdapter(string $adapterName, string $supplierName, string $mode, bool $force): void
    {
        $adapterPath = app_path("Suppliers/{$adapterName}.php");

        if (File::exists($adapterPath) && !$force) {
            $this->warn("Adapter file already exists: {$adapterPath}");
            return;
        }

        $stub = $this->getAdapterStub($adapterName, $supplierName, $mode);
        File::put($adapterPath, $stub);

        $this->info("Created adapter: {$adapterPath}");
    }

    protected function createController(string $controllerName, string $supplierName, bool $force): void
    {
        $controllerPath = app_path("Http/Controllers/{$controllerName}.php");

        if (File::exists($controllerPath) && !$force) {
            $this->warn("Controller file already exists: {$controllerPath}");
            return;
        }

        $stub = $this->getControllerStub($controllerName, $supplierName);
        File::put($controllerPath, $stub);

        $this->info("Created controller: {$controllerPath}");
    }

    protected function getAdapterStub(string $adapterName, string $supplierName, string $mode): string
    {
        $className = $adapterName;
        $supplierClass = Str::studly($supplierName);

        $webhookMethods = $mode === 'webhook' ? '
    public function verify(?Request $request): JsonResponse|bool
    {
        // Implement ' . $supplierName . ' webhook verification logic
        // TODO: Add proper verification (signature, token, etc.)
        return true;
    }

    public function handleEvent(array $event): ?TelemetryDTO
    {
        Log::info("[' . $className . '] Processing ' . $supplierName . ' event", [
            \'event\' => $event
        ]);

        // TODO: Implement ' . $supplierName . '-specific event processing
        return new TelemetryDTO(
            type: \'' . $supplierClass . 'Event\',
            eventId: $event[\'id\'] ?? uniqid(),
            deviceId: $event[\'device_id\'] ?? null,
            occurredAt: $event[\'timestamp\'] ?? null,
            payload: $event
        );
    }' : '';

        $apiMethods = $mode === 'api' ? '
    public function verify(?Request $request): JsonResponse|bool
    {
        // API suppliers don\'t need webhook verification
        return true;
    }

    public function handleEvent(array $event): ?TelemetryDTO
    {
        // API suppliers don\'t handle individual events
        return null;
    }

    public function handleApi(): void
    {
        Log::info("[' . $className . '] Starting ' . $supplierName . ' API data fetch");

        // TODO: Implement ' . $supplierName . ' API data fetching
        // 1. Fetch data from ' . $supplierName . ' API
        // 2. Convert to TelemetryDTO objects
        // 3. Store in ProcessedTelemetry table
        
        // Example implementation:
        // $this->fetchFromApi();
        // $this->storeTelemetry($telemetries);
    }

    protected function fetchFromApi(): array
    {
        // TODO: Implement API fetching logic
        // Use HasFetchLog trait for date range management
        return [];
    }

    protected function storeTelemetry(array $telemetries): void
    {
        // TODO: Store telemetries in ProcessedTelemetry table
        foreach ($telemetries as $dto) {
            ProcessedTelemetry::updateOrCreate(
                [\'supplier\' => $this->name(), \'event_id\' => $dto->eventId],
                [
                    \'type\' => $dto->type,
                    \'device_id\' => $dto->deviceId,
                    \'occurred_at\' => $dto->occurredAt,
                    \'payload\' => $dto->payload,
                    \'status\' => \'pending\',
                ]
            );
        }
    }' : '';

        return "<?php

namespace App\\Suppliers;

use Illuminate\\Http\\Request;
use Illuminate\\Http\\JsonResponse;
use App\\DTOs\\TelemetryDTO;
use App\\Traits\\HasFetchLog;
use App\\Models\\ProcessedTelemetry;
use Illuminate\\Support\\Facades\\Log;

class {$className} extends AbstractSupplierAdapter
{
    use HasFetchLog;

    public function name(): string
    {
        return '{$supplierName}';
    }

    public function mode(): string
    {
        return '{$mode}';
    }{$webhookMethods}{$apiMethods}
}";
    }

    protected function getControllerStub(string $controllerName, string $supplierName): string
    {
        $supplierClass = Str::studly($supplierName);
        $adapterName = $supplierClass . 'Adapter';

        return "<?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\Request;
use Illuminate\\Http\\JsonResponse;
use App\\Suppliers\\{$adapterName};
use App\\Tenants\\TenantForwarder;
use App\\Jobs\\ForwardTelemetryJob;
use Illuminate\\Support\\Facades\\Log;

class {$controllerName} extends Controller
{
    protected {$adapterName} \$adapter;
    protected TenantForwarder \$tenantForwarder;
    
    public function __construct({$adapterName} \$adapter, TenantForwarder \$tenantForwarder)
    {
        \$this->adapter = \$adapter;
        \$this->tenantForwarder = \$tenantForwarder;
    }
    
    /**
     * Handle {$supplierName} webhook with dynamic tenant resolution
     * URL: /webhook/{$supplierName}/{tenant}
     */
    public function handle(Request \$request, string \$tenant): JsonResponse
    {
        \$supplier = '{$supplierName}';
        
        Log::info(\"[{$controllerName}] Webhook received\", [
            'supplier' => \$supplier,
            'tenant' => \$tenant,
            'path' => \$request->path()
        ]);
        
        try {
            // Verify webhook
            \$verificationResult = \$this->adapter->verify(\$request);
            if (\$verificationResult instanceof JsonResponse) {
                return \$verificationResult;
            }
            
            // Process events
            \$events = \$request->json()->all();
            \$processedCount = 0;
            \$errors = [];
            
            foreach (\$events as \$event) {
                try {
                    \$dto = \$this->adapter->handleEvent(\$event);
                    
                    if (\$dto) {
                        // Dispatch forwarding job for reliable delivery
                        ForwardTelemetryJob::dispatch(\$supplier, \$tenant, \$dto);
                        
                        \$processedCount++;
                        Log::info(\"[{$controllerName}] Event dispatched for forwarding\", [
                            'supplier' => \$supplier,
                            'tenant' => \$tenant,
                            'event_id' => \$dto->eventId,
                            'type' => \$dto->type
                        ]);
                    }
                } catch (\\Throwable \$e) {
                    \$errors[] = \"Error processing event: \" . \$e->getMessage();
                    Log::error(\"[{$controllerName}] Error processing event\", [
                        'supplier' => \$supplier,
                        'tenant' => \$tenant,
                        'error' => \$e->getMessage()
                    ]);
                }
            }
            
            return response()->json([
                'message' => '{$supplierName} webhook processed',
                'supplier' => \$supplier,
                'tenant' => \$tenant,
                'processed_count' => \$processedCount,
                'total_events' => count(\$events),
                'errors' => \$errors
            ]);
            
        } catch (\\Throwable \$e) {
            Log::error(\"[{$controllerName}] Webhook processing failed\", [
                'supplier' => \$supplier,
                'tenant' => \$tenant,
                'error' => \$e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Webhook processing failed',
                'message' => \$e->getMessage()
            ], 500);
        }
    }
}";
    }

    protected function updateRoutes(string $supplierName, string $controllerName): void
    {
        $routeFile = base_path('routes/web.php');
        $content = File::get($routeFile);

        // Check if route already exists
        if (strpos($content, "/webhook/{$supplierName}/") !== false) {
            $this->warn("Route already exists for {$supplierName}");
            return;
        }

        $newRoute = "    // {$supplierName} webhook routes\n    Route::post('/webhook/{$supplierName}/{tenant}', [{$controllerName}::class, 'handle'])\n        ->where('tenant', '[a-zA-Z0-9_-]+');\n";

        // Add route before the closing bracket
        $content = str_replace(
            '});',
            $newRoute . '});',
            $content
        );

        File::put($routeFile, $content);
        $this->info("Updated routes file");
    }

    protected function updateConfiguration(string $supplierName, string $mode): void
    {
        $configFile = config_path('machinehub.php');
        $content = File::get($configFile);

        // Check if supplier already exists in config
        if (strpos($content, "'{$supplierName}' =>") !== false) {
            $this->warn("Configuration already exists for {$supplierName}");
            return;
        }

        $config = "'{$supplierName}' => [
    'options' => [
        'mode' => '{$mode}',
        'rate_limit' => '30,1',
        'subscription_name' => '{$supplierName}-sub',
        'allowed_ips' => [''],
    ],
    'tenants' => [
        'yellowbeared' => [
            'webhook_url' => env('" . strtoupper($supplierName) . "_YELLOWBEARED_WEBHOOK_URL'),
            'api_key' => env('" . strtoupper($supplierName) . "_YELLOWBEARED_API_KEY'),
        ],
        'yellowrock' => [
            'webhook_url' => env('" . strtoupper($supplierName) . "_YELLOWROCK_WEBHOOK_URL'),
            'api_key' => env('" . strtoupper($supplierName) . "_YELLOWROCK_API_KEY'),
        ],
    ],
],";

        // Add configuration before the closing bracket
        $content = str_replace(
            '];',
            $config . "\n];",
            $content
        );

        File::put($configFile, $content);
        $this->info("Updated configuration file");
    }
}
