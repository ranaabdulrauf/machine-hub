# Machine Hub - Telemetry Forwarding System

A scalable Laravel application that acts as a bridge between coffee machine suppliers and multi-tenant Dobby platform instances. The system receives telemetry data from various suppliers and intelligently forwards it to the appropriate tenant webhooks based on configuration.

## 🎯 Purpose & Architecture

### What This Application Does

Machine Hub serves as a **telemetry data router** that:

- Receives real-time telemetry data from coffee machine suppliers (WMF, Dejong, Franke, etc.)
- Processes and normalizes the data into a standard format
- Routes the data to the correct tenant instance based on supplier-tenant mapping
- Supports both webhook-based and API-based suppliers
- Provides a scalable architecture for adding new suppliers and tenants

### System Architecture

```
┌─────────────────┐    ┌──────────────┐    ┌─────────────────┐
│   Suppliers     │───▶│ Machine Hub  │───▶│   Tenants       │
│                 │    │              │    │                 │
│ • WMF (Webhook) │    │ • Routing    │    │ • Yellowbeared  │
│ • Dejong (API)  │    │ • Processing │    │ • Yellowrock    │
│ • Franke (TBD)  │    │ • Forwarding │    │ • Hermelin      │
└─────────────────┘    └──────────────┘    └─────────────────┘
```

## 🏗️ Technical Architecture

### Core Components

- **Suppliers**: Adapters for different coffee machine manufacturers
- **Tenants**: Dobby platform instances (Yellowbeared, Yellowrock, etc.)
- **Routing**: Dynamic webhook routing based on supplier-tenant mapping
- **Processing**: Event normalization and validation
- **Forwarding**: HTTP webhook delivery to tenant endpoints

### Data Flow

#### Webhook Suppliers (WMF, Franke)

1. **Ingestion**: Supplier sends webhook to `/webhook/{supplier}/{tenant}`
2. **Processing**: Controller processes event using supplier adapter
3. **Job Dispatch**: `ForwardTelemetryJob` is dispatched to queue
4. **Forwarding**: Job forwards data to tenant's webhook URL
5. **Retry Logic**: Failed forwards are automatically retried

#### API Suppliers (All API Suppliers)

1. **Scheduled Fetch**: `ProcessAllApiSuppliersJob` runs every 5 minutes
2. **Auto-Discovery**: Discovers all API suppliers from registry
3. **API Calls**: Fetches data from each supplier's API
4. **Storage**: Stores telemetry in `ProcessedTelemetry` table
5. **Scheduled Forward**: `ForwardAllApiSuppliersTelemetryJob` runs every 2 minutes
6. **Job Dispatch**: Dispatches `ForwardTelemetryJob` for each tenant
7. **Forwarding**: Jobs forward data to tenant webhooks

## 📊 Current Suppliers

| Supplier   | Mode    | Status     | Routes                     | Jobs                                                               |
| ---------- | ------- | ---------- | -------------------------- | ------------------------------------------------------------------ |
| **WMF**    | Webhook | ✅ Active  | `/webhook/wmf/{tenant}`    | `ForwardTelemetryJob`                                              |
| **Dejong** | API     | ✅ Active  | None                       | `ProcessAllApiSuppliersJob` + `ForwardAllApiSuppliersTelemetryJob` |
| **Franke** | Webhook | 🚧 Planned | `/webhook/franke/{tenant}` | `ForwardTelemetryJob`                                              |

## 🔧 Supplier Integration Modes

### Webhook Mode (Real-time)

**Used by**: WMF, Franke (future)
**Mechanism**: Suppliers send HTTP POST requests to our webhook endpoints
**URL Pattern**: `POST /webhook/{supplier}/{tenant}`
**Example**: `POST /webhook/wmf/yellowbeared`

**WMF Verification**: Uses Azure Event Grid validation
- **Subscription Validation**: Handles `Microsoft.EventGrid.SubscriptionValidationEvent`
- **CloudEvents v1.0**: Supports HTTP OPTIONS for abuse protection
- **Subscription Name**: Validates `aeg-subscription-name` header
- **Headers**: `aeg-event-type: SubscriptionValidation`

**Franke Verification**: Different approach (TBD)
- Each supplier can have its own verification method
- Separate middleware and routes for flexibility

**Advantages**:
- Real-time data delivery
- No polling overhead
- Immediate processing
- **Flexible verification** per supplier

### API Mode (Scheduled Jobs)

**Used by**: Dejong, and any other API suppliers
**Mechanism**: System uses generic scheduled jobs to poll ALL API suppliers
**Jobs**: `ProcessAllApiSuppliersJob` (every 5 min) + `ForwardAllApiSuppliersTelemetryJob` (every 2 min)
**No Routes**: API suppliers have no webhook routes (API-only)

**Advantages**:
- Works with suppliers that don't support webhooks
- **Auto-discovers all API suppliers** from registry
- **Zero configuration** for new API suppliers
- Reliable data retrieval with job-based processing
- Automatic retry logic for failed forwards
- Configurable polling intervals

## 📁 Project Structure

```
app/
├── DTOs/                    # Data Transfer Objects
│   └── TelemetryDTO.php
├── Tenants/                 # Tenant management
│   ├── TenantResolver.php   # Extract tenant from requests
│   └── TenantForwarder.php  # Forward data to tenant webhooks
├── Suppliers/               # Supplier adapters
│   ├── AbstractSupplierAdapter.php
│   ├── WMFAdapter.php       # WMF webhook adapter
│   └── DejongAdapter.php    # Dejong API adapter
├── Http/
│   └── Controllers/
│       ├── WMFController.php      # WMF webhook controller
│       ├── DejongController.php   # Dejong controller (no webhooks)
│       └── FrankeController.php   # Franke webhook controller
├── Jobs/
│   ├── ForwardTelemetryJob.php   # Universal forwarding job
│   ├── ProcessAllApiSuppliersJob.php # Generic API supplier processing
│   ├── ForwardAllApiSuppliersTelemetryJob.php # Generic API supplier forwarding
│   ├── FetchApiSupplierDataJob.php # Individual API supplier fetching
│   └── ForwardApiSupplierTelemetryJob.php # Individual API supplier forwarding
└── Traits/
    └── HasFetchLog.php            # API polling utilities
```

## 🚀 Getting Started

### Prerequisites

- PHP 8.1+
- Laravel 11+
- MySQL/PostgreSQL
- Composer

### Installation

```bash
# Clone repository
git clone <repository-url>
cd machine-hub

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start queue worker (for job processing)
php artisan queue:work

# Start scheduler (for all API suppliers)
php artisan schedule:work
```

## 🔧 Environment Configuration

### Tenant Webhook URLs

Configure tenant webhook URLs using environment variables in your `.env` file:

```env
# Tenant Webhook URLs (replace with your actual tenant URLs)
YELLOWBEARED_WEBHOOK_URL=https://your-yellowbeared-domain.com/webhook/telemetry
YELLOWROCK_WEBHOOK_URL=https://your-yellowrock-domain.com/webhook/telemetry
HERMELIN_WEBHOOK_URL=https://your-hermelin-domain.com/webhook/telemetry

# Optional: API Keys for tenant authentication
YELLOWBEARED_API_KEY=your_yellowbeared_api_key
YELLOWROCK_API_KEY=your_yellowrock_api_key
HERMELIN_API_KEY=your_hermelin_api_key
```

### How Environment Variables Work

The system uses environment variables to dynamically configure tenant webhook URLs:

1. **Add to your `.env` file:**
```env
YELLOWBEARED_WEBHOOK_URL=https://your-yellowbeared-domain.com/webhook/telemetry
YELLOWROCK_WEBHOOK_URL=https://your-yellowrock-domain.com/webhook/telemetry
```

2. **The config automatically uses these variables:**
```php
// In config/machinehub.php
'tenants' => [
    'yellowbeared' => [
        'webhook_url' => env('YELLOWBEARED_WEBHOOK_URL', null),
        'api_key' => env('YELLOWBEARED_API_KEY', null),
    ],
    'yellowrock' => [
        'webhook_url' => env('YELLOWROCK_WEBHOOK_URL', null),
        'api_key' => env('YELLOWROCK_API_KEY', null),
    ],
],
```

3. **Benefits of this approach:**
- ✅ **Environment-specific URLs** (dev, staging, production)
- ✅ **Easy configuration** without code changes
- ✅ **Secure** - URLs not stored in code
- ✅ **Flexible** - Add new tenants by adding env vars
- ✅ **No hardcoded URLs** - all tenant URLs are configurable

**Important**: Replace the placeholder URLs with your actual tenant domains. The system will log warnings if URLs are not configured, but will continue processing.

## 🛠️ Available Commands

### Supplier Management

```bash
# Create a new supplier adapter
php artisan make:adapter AbcAdapter --supplier=abc --mode=api

# List all registered suppliers
php artisan supplier:list
```

### System Commands

```bash
# Start queue worker
php artisan queue:work

# Start scheduler
php artisan schedule:work

# Run tests
php artisan test
```

## 🚀 Scalable Architecture

### Smart Supplier Registry

The system uses a `SupplierRegistry` to automatically manage all suppliers:

```php
// Automatically discovers and processes all API suppliers
$apiSuppliers = SupplierRegistry::getApiSuppliers();
// Returns: ['dejong', 'newsupplier', 'anothersupplier']

// Automatically discovers webhook suppliers
$webhookSuppliers = SupplierRegistry::getWebhookSuppliers();
// Returns: ['wmf', 'franke']
```

### Zero-Configuration Scaling

**Adding a new API supplier:**

1. Run: `php artisan make:adapter NewsupplierAdapter --supplier=newsupplier --mode=api`
2. Implement the `handleApi()` method in the adapter
3. **That's it!** - Automatically discovered and processed by generic jobs

**Adding a new webhook supplier:**

1. Run: `php artisan make:adapter NewsupplierAdapter --supplier=newsupplier --mode=webhook`
2. Implement the `handleEvent()` method in the adapter
3. **That's it!** - Routes and configuration are auto-generated

### Generic Job Processing

- **`ProcessAllApiSuppliersJob`**: Automatically processes ALL API suppliers
- **`ForwardAllApiSuppliersTelemetryJob`**: Automatically forwards ALL API supplier data
- **`ForwardTelemetryJob`**: Universal forwarding job for all suppliers

## 🔄 Job-Based Forwarding System

### ForwardTelemetryJob

All telemetry forwarding uses the `ForwardTelemetryJob` for reliability:

**Features**:
- **Retry Logic**: 3 attempts with exponential backoff
- **Timeout**: 30 seconds per attempt
- **Error Handling**: Comprehensive logging and failure tracking
- **Queue-Based**: Guaranteed delivery with Laravel queues

**Usage**:
```php
// Dispatch forwarding job
ForwardTelemetryJob::dispatch($supplier, $tenant, $dto);
```

### Job Flow

#### Webhook Suppliers

1. **Webhook Received** → Controller processes event
2. **DTO Created** → Supplier adapter normalizes data
3. **Job Dispatched** → `ForwardTelemetryJob` queued
4. **Job Processed** → Forwards to tenant webhook
5. **Retry on Failure** → Automatic retry with backoff

#### API Suppliers

1. **Scheduled Fetch** → `ProcessAllApiSuppliersJob` runs
2. **Auto-Discovery** → Discovers all API suppliers from registry
3. **Data Stored** → Telemetry saved to database for each supplier
4. **Scheduled Forward** → `ForwardAllApiSuppliersTelemetryJob` runs
5. **Jobs Dispatched** → `ForwardTelemetryJob` for each tenant
6. **Jobs Processed** → Forwards to tenant webhooks

## 🔌 Adding New Suppliers

### Quick Setup

Use the `make:adapter` command to automatically create everything:

```bash
# Create webhook supplier
php artisan make:adapter AbcAdapter --supplier=abc --mode=webhook

# Create API supplier
php artisan make:adapter AbcAdapter --supplier=abc --mode=api

# With custom controller name
php artisan make:adapter AbcAdapter --supplier=abc --controller=CustomController

# Overwrite existing files
php artisan make:adapter AbcAdapter --force
```

**Command Options**:
- `--supplier=name` - Supplier name (defaults to adapter name)
- `--mode=webhook|api` - Mode (defaults to webhook)
- `--controller=name` - Controller name (defaults to {Supplier}Controller)
- `--force` - Overwrite existing files

**What it creates**:
- ✅ Adapter file with proper structure
- ✅ Controller file (for webhook suppliers)
- ✅ Routes (for webhook suppliers)
- ✅ Configuration entries
- ✅ Auto-registers supplier

### Manual Setup

If you prefer to create files manually:

1. **Create Adapter** in `app/Suppliers/`
2. **Create Controller** in `app/Http/Controllers/` (webhook suppliers only)
3. **Add Routes** in `routes/web.php` (webhook suppliers only)
4. **Update Configuration** in `config/machinehub.php`

**Note**: API suppliers are automatically processed by scheduled jobs - no manual setup needed!

## 👥 Adding New Tenants

### Step 1: Add Environment Variables

Add the new tenant's webhook URL to your `.env` file:

```env
# Add new tenant webhook URL
NEWTENANT_WEBHOOK_URL=https://your-newtenant-domain.com/webhook/telemetry
NEWTENANT_API_KEY=your_newtenant_api_key
```

### Step 2: Update Supplier Configurations

For each existing supplier, add the new tenant in `config/machinehub.php`:

```php
'wmf' => [
    'tenants' => [
        'yellowbeared' => [...],
        'yellowrock' => [...],
        'hermelin' => [...],
        'newtenant' => [  // Add new tenant
            'webhook_url' => env('NEWTENANT_WEBHOOK_URL'),
            'api_key' => env('NEWTENANT_API_KEY'),
        ],
    ],
],
```

### Step 3: Test New Tenant

```bash
# Test webhook for new tenant
curl -X POST http://localhost:8000/webhook/wmf/newtenant \
  -H "Content-Type: application/json" \
  -d '{"eventType": "Dispensing", "data": {"DeviceId": "test-device"}}'
```

## 🔄 Scaling Strategies

### Horizontal Scaling

- **Load Balancer**: Deploy multiple instances behind a load balancer
- **Queue Workers**: Scale queue workers for API polling
- **Database**: Use read replicas for telemetry queries

### Vertical Scaling

- **Memory**: Increase PHP memory for large event processing
- **CPU**: Add more cores for concurrent webhook processing
- **Storage**: Use faster storage for telemetry data

### Monitoring & Observability

- **Logs**: All operations are logged with structured data
- **Metrics**: Track webhook success rates, processing times
- **Alerts**: Set up alerts for failed webhook deliveries
- **Health Checks**: Monitor system health and dependencies

## 🧪 Testing

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

### Test Files

- **ApiSuppliersTest**: Tests API supplier functionality (Dejong)
- **WebhookSuppliersTest**: Tests webhook supplier functionality (WMF, Franke)

## 📊 Monitoring & Debugging

### Logs

- **Location**: `storage/logs/laravel.log`
- **Structured Logging**: JSON format with context
- **Log Levels**: INFO, WARNING, ERROR

### Database Tables

- **`processed_telemetries`**: Stores telemetry data for API mode
- **`supplier_fetch_logs`**: Tracks API polling status

### Health Checks

```bash
# Check system health
curl http://localhost:8000/health
```

## 🚨 Error Handling

### Webhook Failures

- Automatic retry with exponential backoff
- Dead letter queue for persistent failures
- Detailed error logging with context

### API Polling Failures

- Graceful degradation on API errors
- Configurable retry intervals
- Alert notifications for persistent failures

### Tenant Failures

- Individual tenant failure doesn't affect others
- Detailed error reporting per tenant
- Configurable timeout settings

## 🔒 Security Considerations

### Webhook Security

- IP whitelisting for supplier webhooks
- Signature verification where supported
- Rate limiting to prevent abuse

### API Security

- API key authentication for supplier APIs
- Secure credential storage
- Request/response logging for audit

### Data Privacy

- No sensitive data stored in logs
- Secure transmission to tenant webhooks
- Configurable data retention policies

## 📈 Performance Optimization

### Caching

- Configuration caching for tenant mappings
- Response caching for frequently accessed data
- Database query optimization

### Queue Management

- Priority queues for different event types
- Batch processing for API polling
- Dead letter queue handling

### Database Optimization

- Indexed columns for fast lookups
- Partitioning for large telemetry tables
- Regular cleanup of old data

## 🛠️ Development Guidelines

### Code Standards

- Follow PSR-12 coding standards
- Use type hints and return types
- Comprehensive error handling
- Detailed logging and documentation

### Testing Requirements

- Run tests before deployment: `php artisan test`
- Ensure both API and webhook suppliers are tested

### Deployment

- Blue-green deployment strategy
- Database migration safety
- Configuration management
- Rollback procedures

## 📞 Support & Maintenance

### Troubleshooting

1. Check logs for error details
2. Verify tenant configuration
3. Test webhook endpoints manually
4. Monitor queue worker status

### Maintenance Tasks

- Regular log rotation
- Database cleanup
- Performance monitoring
- Security updates

### Contact

For technical support or questions about scaling the system, contact the development team.

---

**Version**: 1.0.0  
**Last Updated**: 2024  
**Laravel Version**: 11.x  
**PHP Version**: 8.1+