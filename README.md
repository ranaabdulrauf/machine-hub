# Machine Hub - Telemetry Forwarding System

A Laravel application that receives telemetry from coffee machine suppliers and forwards it to tenant webhooks. This guide will help you understand, configure, and extend the system.

## 🎯 What This System Does

**Simple Explanation**:

-   Coffee machine suppliers (WMF, Dejong, Franke) send us telemetry data
-   We process and forward this data to different tenant platforms (Yellowbeared, Yellowrock, Hermelin)
-   Each tenant has their own webhook URL where we send the data

**Data Flow**:

```
Supplier → Machine Hub → Tenant Webhook
```

## 📁 Project Structure

```
app/
├── DTOs/
│   └── TelemetryDTO.php              # Standard data format
├── Tenants/
│   ├── TenantResolver.php            # Extracts tenant from URL
│   └── TenantForwarder.php           # Sends data to tenant webhooks
├── Suppliers/                        # Supplier adapters (one per supplier)
│   ├── AbstractSupplierAdapter.php   # Base class for all adapters
│   ├── WMFAdapter.php               # WMF webhook adapter
│   └── DejongAdapter.php            # Dejong API adapter
├── Http/Controllers/                 # Webhook controllers (one per supplier)
│   ├── WMFController.php            # Handles WMF webhooks
│   ├── DejongController.php         # Handles Dejong (API only)
│   └── FrankeController.php         # Handles Franke webhooks
├── Jobs/                            # Background jobs
│   ├── ForwardTelemetryJob.php      # Forwards data to tenants
│   ├── ProcessAllApiSuppliersJob.php # Fetches from all API suppliers
│   └── ForwardAllApiSuppliersTelemetryJob.php # Forwards API data
└── Traits/
    └── HasFetchLog.php              # Helper for API polling
```

## 🚀 Quick Start (5 Minutes)

### 1. Install and Setup

```bash
# Clone and install
git clone <repository-url>
cd machine-hub
composer install

# Setup environment
cp .env.example .env
php artisan key:generate
php artisan migrate

# Start the system
php artisan queue:work &
php artisan schedule:work &
```

### 2. Configure Your First Tenant

Add to your `.env` file:

```env
YELLOWBEARED_WEBHOOK_URL=https://yellowbeared.dobby.com/webhook/telemetry
YELLOWROCK_WEBHOOK_URL=https://yellowrock.dobby.com/webhook/telemetry
HERMELIN_WEBHOOK_URL=https://hermelin.dobby.com/webhook/telemetry
```

### 3. Test the System

```bash
# Test WMF webhook
curl -X POST http://localhost:8000/webhook/wmf/yellowbeared \
  -H "Content-Type: application/json" \
  -d '{"eventType": "Dispensing", "data": {"DeviceId": "test-device"}}'

# Run tests
php artisan test
```

## 👥 How to Create a New Tenant

### Step 1: Add Environment Variable

Add to your `.env` file:

```env
NEWTENANT_WEBHOOK_URL=https://newtenant.dobby.com/webhook/telemetry
NEWTENANT_API_KEY=newtenant_api_key_123
```

### Step 2: Update All Supplier Configs

Add the tenant to each supplier in `config/machinehub.php`:

```php
'newtenant' => [
    'webhook_url' => env('NEWTENANT_WEBHOOK_URL', null),
    'api_key' => env('NEWTENANT_API_KEY', null),
],
```

### Step 3: Test

```bash
# Test with new tenant
curl -X POST http://localhost:8000/webhook/wmf/newtenant \
  -H "Content-Type: application/json" \
  -d '{"eventType": "Dispensing", "data": {"DeviceId": "test-device"}}'
```

## 🔧 Supplier Commands

### Create New Supplier

**Command:**
```bash
php artisan supplier:create {name} [options]
```

**Description:** Creates a new supplier with adapter and optional controller

**Available Flags:**

| Flag           | Required | Description                                        | Example                      |
| -------------- | -------- | -------------------------------------------------- | ---------------------------- |
| `--mode`       | No       | Mode: webhook or api (default: webhook)            | `--mode=api`                 |
| `--controller` | No       | Controller name (defaults to {Supplier}Controller) | `--controller=WMFController` |
| `--force`      | No       | Overwrite existing files                           | `--force`                    |

### Examples

#### Create Webhook Supplier

```bash
php artisan supplier:create wmf --mode=webhook --controller=WMFController
```

**Result:**

```
Creating supplier: wmf
Mode: webhook
Adapter: WmfAdapter
Controller: WMFController
Created adapter: app/Suppliers/WmfAdapter.php
Created controller: app/Http/Controllers/WMFController.php
✅ Supplier created successfully!
✅ Controller created: WMFController
📝 Next steps:
   1. Add route to routes/web.php:
      Route::post('/webhook/wmf/{tenant}', [WMFController::class, 'handle'])
   2. Add configuration to config/machinehub.php:
      'wmf' => [
          'options' => ['mode' => 'webhook', 'rate_limit' => '30,1'],
          'tenants' => [/* add your tenants */]
      ]
   3. Add environment variables to .env:
      WMF_YELLOWBEARED_WEBHOOK_URL=https://yellowbeared.dobby.com/webhook/telemetry
   4. Implement verification and event handling logic in adapter
```

#### Create API Supplier

```bash
php artisan supplier:create dejong --mode=api
```

**Result:**

```
Creating supplier: dejong
Mode: api
Adapter: DejongAdapter
Controller: dejongController
Created adapter: app/Suppliers/DejongAdapter.php
✅ Supplier created successfully!
✅ API supplier - will be processed by scheduled jobs
📝 Next steps:
   1. Add configuration to config/machinehub.php:
      'dejong' => [
          'options' => ['mode' => 'api', 'rate_limit' => '30,1'],
          'tenants' => [/* add your tenants */]
      ]
   2. Add environment variables to .env:
      DEJONG_YELLOWBEARED_WEBHOOK_URL=https://yellowbeared.dobby.com/webhook/telemetry
   3. Implement API fetching logic in adapter
```

### List All Suppliers

**Command:**
```bash
php artisan supplier:list
```

**Description:** Lists all registered suppliers with their details

**Result:**
```
Registered Suppliers:

+----------+---------+-----------------------------+-----------+
| Supplier | Mode    | Adapter Class               | Status    |
+----------+---------+-----------------------------+-----------+
| wmf      | webhook | App\Suppliers\WMFAdapter    | ✅ Active |
| dejong   | api     | App\Suppliers\DejongAdapter | ✅ Active |
| franke   | webhook | App\Suppliers\FrankeAdapter | ✅ Active |
+----------+---------+-----------------------------+-----------+

Total: 3 suppliers
Webhook: 2 suppliers
API: 1 suppliers
```

## 🧪 Testing

### Available Tests

| Test File              | Description                                        |
| ---------------------- | -------------------------------------------------- |
| `ApiSuppliersTest`     | Tests API supplier functionality (Dejong)          |
| `WebhookSuppliersTest` | Tests webhook supplier functionality (WMF, Franke) |

### Run Tests

```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --filter=ApiSuppliersTest
php artisan test --filter=WebhookSuppliersTest

# Run specific test
php artisan test --filter="can discover api suppliers"
```

### Test Results

```bash
PS D:\laragon\www\machine-hub> php artisan test
PASS  Tests\Feature\ApiSuppliersTest
✓ can discover api suppliers
✓ can process api supplier data
✓ can forward api supplier telemetry
✓ handles api supplier errors
✓ updates supplier fetch status

PASS  Tests\Feature\WebhookSuppliersTest
✓ can discover webhook suppliers
✓ handles wmf webhook events
✓ handles franke webhook events
✓ processes multiple events
✓ creates telemetry dto
✓ handles webhook errors
✓ handles different tenants
✓ logs webhook activity
✓ handles wmf subscription validation
✓ handles wmf options request for abuse protection
✓ rejects options request without origin header
✓ handles azure event grid validation with headers
✓ verifies tenant forwarding urls use dynamic environment variables
✓ logs warning when tenant webhook url is not configured

Tests:  19 passed
Time:   0.45s
```

---

**Need help?** Check the logs at `storage/logs/laravel.log` and run `php artisan test` to verify everything is working.
