# Google Merchant Center Integration Package

A Laravel package for seamless Google Merchant Center product synchronization with **independent tables** and **optional per-product sync control**.

## Installation

```bash
composer require manu/gmc-integration
```

## Setup

1. **Publish config:**
```bash
php artisan vendor:publish --tag=gmc-config
```

2. **Publish migrations (optional):**
```bash
php artisan vendor:publish --tag=gmc-migrations
```

3. **Run the migrations:**
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
        'name', 'description', 'price', 'stock', 'image_url', 
        'brand', 'sku', 'status'
    ];
    
    public function prepareGMCData(): array
    {
        return [
            'offerId' => (string) $this->id,
            'title' => $this->name,
            'description' => $this->description,
            'link' => url("/products/{$this->id}"),
            'imageLink' => $this->image_url,
            'price' => ['value' => (string) $this->price, 'currency' => 'USD'],
            'availability' => $this->stock > 0 ? 'in stock' : 'out of stock',
            'brand' => $this->brand,
            'condition' => 'new'
        ];
    }
}
```

## Database Structure

The package creates two independent tables:

### `gmc_products` Table
- `product_id` - Reference to your product
- `product_type` - Model class name (e.g., 'App\Models\Product')
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

- ✅ **Independent tables** - No modifications to your existing tables
- ✅ **Clean model** - All GMC methods are in the trait, keeping your model clean
- ✅ **Optional per-product sync control** - Enable/disable sync for individual products
- ✅ **Manual syncing** - Sync products when you want to
- ✅ **Auto-sync on status change to inactive** - Automatically sync when product becomes inactive
- ✅ **Auto-sync on delete** - Automatically remove from GMC when deleted
- ✅ **Bulk operations** with batch processing
- ✅ **Error handling** with retry logic and comprehensive logging
- ✅ **Data validation** before syncing
- ✅ **Rate limiting** to avoid API limits
- ✅ **Cache protection** against duplicate syncs
- ✅ **Async processing** for better performance
- ✅ **Sync history tracking** - Complete audit trail

## Sync Behavior

### Automatic Sync (Only on specific events)
- ✅ **Status change to inactive** - Syncs when `status` field changes to `'inactive'`
- ✅ **Delete** - Removes product from GMC when model is deleted
- ❌ **Create** - No automatic sync on product creation
- ❌ **Update** - No automatic sync on general updates

### Manual Sync
```php
// Sync a single product manually
$product->syncwithgmc();

// Or use the alternative method
$product->syncToGMC();
```

## Optional Per-Product Sync Control

### Method 1: Using trait methods
```php
// Enable sync for a product
$product->enableGMCSync();

// Disable sync for a product
$product->disableGMCSync();

// Check if sync is enabled
if ($product->shouldSyncToGMC()) {
    echo "Sync is enabled for this product";
}
```

### Method 2: Override shouldSyncToGMC()
```php
public function shouldSyncToGMC(): bool
{
    // Only sync premium products
    return $this->is_premium && $this->status === 'active';
}
```

## Usage Examples

### Manual Syncing
```php
// Single product
$product->syncwithgmc();

// Bulk sync with progress tracking
$gmcService = app(GMCService::class);
$result = $gmcService->syncMultipleProducts($products, 25); // 25 per batch
```

### Status Change Example
```php
// This will automatically sync to GMC when status changes to inactive
$product->update(['status' => 'inactive']);

// This will NOT automatically sync (status is not 'inactive')
$product->update(['status' => 'active']);

// This will NOT automatically sync (no status change)
$product->update(['price' => 29.99]);
```

### Working with GMC Data
```php
// Get sync status
$status = $product->getGMCSyncStatus();
// Returns: ['is_synced' => true, 'gmc_id' => '123', 'last_sync' => '2024-01-01T00:00:00Z', 'sync_enabled' => true, 'sync_status' => 'synced', 'last_error' => null]

// Check if synced
if ($product->isSyncedWithGMC()) {
    echo "Product is synced with GMC";
}

// Get sync logs
$logs = $product->getGMCSyncLogs();

// Get last successful sync
$lastSync = $product->getLastSuccessfulGMCSync();

// Get last error
$lastError = $product->getLastGMCError();
```

### Artisan Commands
```bash
# Sync all products
php artisan gmc:sync-all

# Sync with filters
php artisan gmc:sync-all --filter="status=active"

# Dry run to see what would be synced
php artisan gmc:sync-all --dry-run

# Force sync even if recently synced
php artisan gmc:sync-all --force

# Use smaller chunks for memory efficiency
php artisan gmc:sync-all --chunk=25
```

## Configuration Options

```env
# Basic settings
GMC_MERCHANT_ID=your_merchant_id
GMC_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
GMC_AUTO_SYNC=true

# Performance settings
GMC_BATCH_SIZE=50
GMC_RETRY_ATTEMPTS=3
GMC_RETRY_DELAY=1000

# Cache settings
GMC_CACHE_DUPLICATE_SYNCS=true
GMC_CACHE_DURATION=300

# Sync control fields
GMC_SYNC_ENABLED_FIELD=sync_enabled
GMC_SYNC_ENABLED_FIELD_ALT=gmc_sync_enabled

# Logging
GMC_LOG_SYNC_EVENTS=true
GMC_LOG_LEVEL=info
```

## Advanced Features

### Batch Processing
```php
$gmcService = app(GMCService::class);
$gmcService->setBatchSize(25); // Process 25 products at a time
$result = $gmcService->syncMultipleProducts($products);
```

### Error Handling
```php
try {
    $product->syncwithgmc();
} catch (\Exception $e) {
    Log::error('GMC Sync failed: ' . $e->getMessage());
    // Handle error
}
```

### Sync History
```php
use Manu\GMCIntegration\Models\GMCSyncLog;

// Get all sync logs
$logs = GMCSyncLog::with('gmcProduct')->latest()->get();

// Get failed syncs
$failedLogs = GMCSyncLog::where('status', 'failed')->get();

// Get performance metrics
$avgResponseTime = GMCSyncLog::where('status', 'success')
    ->avg('response_time_ms');
```

## Performance Optimizations

- **Batch processing** to handle large datasets efficiently
- **Rate limiting** to avoid API throttling
- **Cache protection** against duplicate syncs
- **Async processing** for better response times
- **Retry logic** for failed operations
- **Memory efficient** chunking for bulk operations
- **Independent tables** - No impact on your existing database performance 