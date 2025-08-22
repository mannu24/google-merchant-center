# Google Merchant Center Integration Package

A Laravel package for seamless Google Merchant Center product synchronization with independent tables and optional per-product sync control.

## Installation

```bash
composer require manu/gmc-integration
```

## Setup

1. **Publish config:**
```bash
php artisan vendor:publish --tag=gmc-config
```

2. **Publish migrations:**
```bash
php artisan vendor:publish --tag=gmc-migrations
```

3. **Run migrations:**
```bash
php artisan migrate
```

4. **Set environment variables:**
```env
GMC_MERCHANT_ID=your_merchant_id
GMC_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
GMC_AUTO_SYNC=true
GMC_THROW_EXCEPTIONS=false
GMC_BATCH_SIZE=50
GMC_RETRY_ATTEMPTS=3
```

5. **Add trait to your product model:**
```php
use Manu\GMCIntegration\Traits\SyncsWithGMC;

class Product extends Model
{
    use SyncsWithGMC;
    
    protected $fillable = [
        'title', 'description', 'price', 'quantity', 'image_url', 
        'brand', 'sku', 'status'
    ];
    
    public function prepareGMCData(): array
    {
        return [
            'offerId' => $this->sku ?: (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'link' => url("/products/{$this->id}"),
            'imageLink' => $this->image_url,
            'price' => ['value' => (string) $this->price, 'currency' => 'USD'],
            'availability' => $this->quantity > 0 ? 'in stock' : 'out of stock',
            'brand' => $this->brand,
            'condition' => 'new'
        ];
    }
}
```

## Database Structure

### `gmc_products` Table
- `product_id` - Reference to your product
- `product_type` - Model class name
- `sync_enabled` - Control sync per product
- `gmc_product_id` - GMC's product ID
- `gmc_last_sync` - Last sync timestamp
- `sync_status` - Current sync status
- `last_error` - Last error message

### `gmc_sync_logs` Table
- Tracks all sync attempts
- Stores request/response data
- Performance metrics
- Error details

## Features

- Independent tables - No modifications to existing tables
- Clean model - All GMC methods in trait
- Optional per-product sync control
- Manual and automatic syncing
- Bulk operations with batch processing
- Error handling with retry logic
- Data validation before syncing
- Rate limiting to avoid API limits
- Cache protection against duplicate syncs
- Async processing for better performance
- Sync history tracking

## Usage Examples

### Manual Syncing
```php
$product->syncwithgmc();
$product->syncToGMC();
$product->forceSyncToGMC();
```

### Bulk Operations
```php
$gmcService = app(GMCService::class);
$result = $gmcService->syncMultipleProducts($products, 25);
```

### Sync Status
```php
$status = $product->getGMCSyncStatus();
if ($product->isSyncedWithGMC()) {
    echo "Product is synced with GMC";
}
```

### Artisan Commands
```bash
php artisan gmc:sync-all
php artisan gmc:sync-all --filter="status=active"
php artisan gmc:sync-all --dry-run
php artisan gmc:sync-all --force
php artisan gmc:sync-all --chunk=25
```

## Configuration

```env
GMC_MERCHANT_ID=your_merchant_id
GMC_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
GMC_AUTO_SYNC=true
GMC_BATCH_SIZE=50
GMC_RETRY_ATTEMPTS=3
GMC_RETRY_DELAY=1000
GMC_CACHE_DUPLICATE_SYNCS=true
GMC_CACHE_DURATION=300
GMC_LOG_SYNC_EVENTS=true
GMC_LOG_LEVEL=info
```

## Advanced Features

### Batch Processing
```php
$gmcService = app(GMCService::class);
$gmcService->setBatchSize(25);
$result = $gmcService->syncMultipleProducts($products);
```

### Error Handling
```php
try {
    $product->syncwithgmc();
} catch (\Exception $e) {
    Log::error('GMC Sync failed: ' . $e->getMessage());
}
```

### Sync History
```php
use Manu\GMCIntegration\Models\GMCSyncLog;

$logs = GMCSyncLog::with('gmcProduct')->latest()->get();
$failedLogs = GMCSyncLog::where('status', 'failed')->get();
$avgResponseTime = GMCSyncLog::where('status', 'success')->avg('response_time_ms');
```

## Performance Optimizations

- Batch processing for large datasets
- Rate limiting to avoid API throttling
- Cache protection against duplicate syncs
- Async processing for better response times
- Retry logic for failed operations
- Memory efficient chunking
- Independent tables for better performance 