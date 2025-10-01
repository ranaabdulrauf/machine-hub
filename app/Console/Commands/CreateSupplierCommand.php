<?php

namespace App\Console\Commands;

use App\Suppliers\SupplierRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreateSupplierCommand extends Command
{
    protected $signature = 'supplier:create {name} 
                            {--mode=webhook : Mode (webhook|api)}
                            {--controller= : Controller name (defaults to {Supplier}Controller)}
                            {--force : Overwrite existing files}';
    protected $description = 'Create a new supplier with adapter and optional controller';

    public function handle()
    {
        $supplierName = $this->argument('name');
        $mode = $this->option('mode');
        $controllerName = $this->option('controller') ?: ucfirst($supplierName) . 'Controller';
        $adapterName = ucfirst($supplierName) . 'Adapter';
        $adapterClass = "App\\Suppliers\\{$adapterName}";
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

        $this->info("Creating supplier: {$supplierName}");
        $this->info("Mode: {$mode}");
        $this->info("Adapter: {$adapterName}");
        $this->info("Controller: {$controllerName}");

        // Create adapter file
        $this->createAdapterFile($supplierName, $mode, $adapterClass, $force);

        // Create controller if webhook supplier
        if ($mode === 'webhook') {
            $this->createControllerFile($supplierName, $controllerName, $force);
        }

        // Register supplier
        SupplierRegistry::register($supplierName, $adapterClass);

        $this->info("✅ Supplier created successfully!");

        if ($mode === 'webhook') {
            $this->info("✅ Controller created: {$controllerName}");
            $this->info("📝 Next steps:");
            $this->info("   1. Add route to routes/web.php:");
            $this->info("      Route::post('/webhook/{$supplierName}/{tenant}', [{$controllerName}::class, 'handle'])");
            $this->info("   2. Add configuration to config/machinehub.php:");
            $this->info("      '{$supplierName}' => [");
            $this->info("          'options' => ['mode' => 'webhook', 'rate_limit' => '30,1'],");
            $this->info("          'tenants' => [/* add your tenants */]");
            $this->info("      ]");
            $this->info("   3. Add environment variables to .env:");
            $this->info("      " . strtoupper($supplierName) . "_YELLOWBEARED_WEBHOOK_URL=https://yellowbeared.dobby.com/webhook/telemetry");
            $this->info("   4. Implement verification and event handling logic in adapter");
        } else {
            $this->info("✅ API supplier - will be processed by scheduled jobs");
            $this->info("📝 Next steps:");
            $this->info("   1. Add configuration to config/machinehub.php:");
            $this->info("      '{$supplierName}' => [");
            $this->info("          'options' => ['mode' => 'api', 'rate_limit' => '30,1'],");
            $this->info("          'tenants' => [/* add your tenants */]");
            $this->info("      ]");
            $this->info("   2. Add environment variables to .env:");
            $this->info("      " . strtoupper($supplierName) . "_YELLOWBEARED_WEBHOOK_URL=https://yellowbeared.dobby.com/webhook/telemetry");
            $this->info("   3. Implement API fetching logic in adapter");
        }

        return 0;
    }

    protected function createAdapterFile(string $name, string $mode, string $adapterClass, bool $force): void
    {
        $adapterName = class_basename($adapterClass);
        $adapterPath = app_path("Suppliers/{$adapterName}.php");

        if (File::exists($adapterPath) && !$force) {
            $this->warn("Adapter file already exists: {$adapterPath}");
            return;
        }

        $stub = $this->getAdapterStub($name, $mode, $adapterClass);
        File::put($adapterPath, $stub);

        $this->info("Created adapter: {$adapterPath}");
    }

    protected function createControllerFile(string $supplierName, string $controllerName, bool $force): void
    {
        $controllerPath = app_path("Http/Controllers/{$controllerName}.php");

        if (File::exists($controllerPath) && !$force) {
            $this->warn("Controller file already exists: {$controllerPath}");
            return;
        }

        $stub = $this->getControllerStub($supplierName, $controllerName);
        File::put($controllerPath, $stub);

        $this->info("Created controller: {$controllerPath}");
    }

    protected function getAdapterStub(string $name, string $mode, string $adapterClass): string
    {
        $className = class_basename($adapterClass);
        $namespace = str_replace("\\{$className}", '', $adapterClass);

        $webhookMethods = $mode === 'webhook' ? '
    public function verify(?Request $request): JsonResponse|bool
    {
        // TODO: Implement ' . $name . ' webhook verification
        return true;
    }

    public function handleEvent(array $event): ?TelemetryDTO
    {
        // TODO: Convert ' . $name . ' event to TelemetryDTO
        return null;
    }' : '';

        $apiMethods = $mode === 'api' ? '
    public function verify(?Request $request): JsonResponse|bool
    {
        return true;
    }

    public function handleEvent(array $event): ?TelemetryDTO
    {
        return null;
    }

    public function handleApi(): void
    {
        // TODO: Implement ' . $name . ' API data fetching
    }' : '';

        return "<?php

namespace {$namespace};

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\DTOs\TelemetryDTO;

class {$className} extends AbstractSupplierAdapter
{
    public function name(): string
    {
        return '{$name}';
    }

    public function mode(): string
    {
        return '{$mode}';
    }{$webhookMethods}{$apiMethods}
}";
    }

    protected function getControllerStub(string $supplierName, string $controllerName): string
    {
        $adapterName = ucfirst($supplierName) . 'Adapter';

        return "<?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\Request;
use Illuminate\\Http\\JsonResponse;
use App\\Suppliers\\{$adapterName};
use App\\Jobs\\ForwardTelemetryJob;

class {$controllerName} extends Controller
{
    public function __construct(private {$adapterName} \$adapter) {}
    
    public function handle(Request \$request, string \$tenant): JsonResponse
    {
        \$supplier = '{$supplierName}';
        
        // TODO: Implement webhook verification
        \$verification = \$this->adapter->verify(\$request);
        if (\$verification instanceof JsonResponse) {
            return \$verification;
        }
        
        // TODO: Process events and dispatch jobs
        \$events = \$request->json()->all();
        if (!is_array(\$events)) \$events = [\$events];
        
        foreach (\$events as \$event) {
            \$dto = \$this->adapter->handleEvent(\$event);
            if (\$dto) {
                ForwardTelemetryJob::dispatch(\$supplier, \$tenant, \$dto);
            }
        }
        
        return response()->json(['status' => 'success']);
    }
}";
    }
}
