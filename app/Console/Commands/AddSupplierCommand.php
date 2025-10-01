<?php

namespace App\Console\Commands;

use App\Suppliers\SupplierRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AddSupplierCommand extends Command
{
    protected $signature = 'supplier:add {name} {mode} {--adapter=}';
    protected $description = 'Add a new supplier to the system';

    public function handle()
    {
        $name = $this->argument('name');
        $mode = $this->argument('mode');
        $adapterClass = $this->option('adapter') ?: "App\\Suppliers\\" . ucfirst($name) . "Adapter";

        // Validate mode
        if (!in_array($mode, ['webhook', 'api'])) {
            $this->error("Mode must be 'webhook' or 'api'");
            return 1;
        }

        // Check if supplier already exists
        if (SupplierRegistry::exists($name)) {
            $this->error("Supplier '{$name}' already exists");
            return 1;
        }

        // Create adapter file
        $this->createAdapterFile($name, $mode, $adapterClass);

        // Register supplier
        SupplierRegistry::register($name, $adapterClass);

        // Update routes if webhook supplier
        if ($mode === 'webhook') {
            $this->updateRoutes($name);
        }

        // Update configuration
        $this->updateConfiguration($name, $mode);

        $this->info("Supplier '{$name}' added successfully!");
        $this->info("Mode: {$mode}");
        $this->info("Adapter: {$adapterClass}");

        if ($mode === 'webhook') {
            $this->info("Route added: /webhook/{$name}/{tenant}");
        } else {
            $this->info("API supplier - will be processed by scheduled jobs");
        }

        return 0;
    }

    protected function createAdapterFile(string $name, string $mode, string $adapterClass): void
    {
        $adapterName = class_basename($adapterClass);
        $adapterPath = app_path("Suppliers/{$adapterName}.php");

        if (File::exists($adapterPath)) {
            $this->warn("Adapter file already exists: {$adapterPath}");
            return;
        }

        $stub = $this->getAdapterStub($name, $mode, $adapterClass);
        File::put($adapterPath, $stub);

        $this->info("Created adapter: {$adapterPath}");
    }

    protected function getAdapterStub(string $name, string $mode, string $adapterClass): string
    {
        $className = class_basename($adapterClass);
        $namespace = str_replace("\\{$className}", '', $adapterClass);

        return "<?php

namespace {$namespace};

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\DTOs\TelemetryDTO;
use Illuminate\Support\Facades\Log;

class {$className} extends AbstractSupplierAdapter
{
    public function name(): string
    {
        return '{$name}';
    }

    public function mode(): string
    {
        return '{$mode}';
    }

    public function verify(?Request \$request): JsonResponse|bool
    {
        // Implement {$name} webhook verification logic
        return true;
    }

    public function handleEvent(array \$event): ?TelemetryDTO
    {
        Log::info(\"[{$className}] Processing {$name} event\", [
            'event' => \$event
        ]);

        // TODO: Implement {$name}-specific event processing
        return new TelemetryDTO(
            type: '{$name}Event',
            eventId: \$event['id'] ?? uniqid(),
            deviceId: \$event['device_id'] ?? null,
            occurredAt: \$event['timestamp'] ?? null,
            payload: \$event
        );
    }" . ($mode === 'api' ? "

    public function handleApi(): void
    {
        // TODO: Implement {$name} API data fetching
        // Fetch data from {$name} API and store in ProcessedTelemetry table
    }" : '') . "
}";
    }

    protected function updateRoutes(string $name): void
    {
        $routeFile = base_path('routes/web.php');
        $content = File::get($routeFile);

        $newRoute = "    // {$name} webhook routes\n    Route::post('/webhook/{$name}/{tenant}', [{$name}Controller::class, 'handle'])\n        ->where('tenant', '[a-zA-Z0-9_-]+');\n";

        // Add route before the closing bracket
        $content = str_replace(
            '});',
            $newRoute . '});',
            $content
        );

        File::put($routeFile, $content);
        $this->info("Updated routes file");
    }

    protected function updateConfiguration(string $name, string $mode): void
    {
        $configFile = config_path('machinehub.php');
        $content = File::get($configFile);

        $config = "'{$name}' => [
    'options' => [
        'mode' => '{$mode}',
        'rate_limit' => '30,1',
        'subscription_name' => '{$name}-sub',
        'allowed_ips' => [''],
    ],
    'tenants' => [
        'yellowbeared' => [
            'webhook_url' => env('" . strtoupper($name) . "_YELLOWBEARED_WEBHOOK_URL'),
            'api_key' => env('" . strtoupper($name) . "_YELLOWBEARED_API_KEY'),
        ],
        'yellowrock' => [
            'webhook_url' => env('" . strtoupper($name) . "_YELLOWROCK_WEBHOOK_URL'),
            'api_key' => env('" . strtoupper($name) . "_YELLOWROCK_API_KEY'),
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
