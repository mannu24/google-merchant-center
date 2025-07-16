# Google Merchant Center Integration Package

A Laravel package for seamless Google Merchant Center product synchronization with **optional per-product sync control**.

## Installation

```bash
composer require manu/gmc-integration
```

## Setup

1. **Publish config:**
```bash
php artisan vendor:publish --tag=gmc-config
```

2. **Create migration for GMC fields:**
```bash
php artisan make:migration add_gmc_fields_to_products_table
```

3. **Add the migration content:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // GMC sync control fields
            $table->boolean('sync_enabled')->default(true)->after('status');
            
            // GMC tracking fields
            $table->string('gmc_product_id')->nullable()->after('sync_enabled');
            $table->timestamp('gmc_last_sync')->nullable()->after('gmc_product_id');
            
            // Indexes for better performance
            $table->index(['sync_enabled', 'status']);
            $table->index('gmc_product_id');
            $table->index('gmc_last_sync');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sync_enabled', 'status']);
            $table->dropIndex(['gmc_product_id']);
            $table->dropIndex(['gmc_last_sync']);
            
            $table->dropColumn([
                'sync_enabled',
                'gmc_product_id',
                'gmc_last_sync'
            ]);
        });
    }
};
```

4. **Run the migration:**
```bash
php artisan migrate
```

5. **Set environment variables:**
```env
GMC_MERCHANT_ID=your_merchant_id
GMC_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
GMC_AUTO_SYNC=true
GMC_THROW_EXCEPTIONS=false
GMC_BATCH_SIZE=50
GMC_RETRY_ATTEMPTS=3
```

6. **Add trait to your product model:**
```php
use Manu\GMCIntegration\Traits\SyncsWithGMC;

class Product extends Model
{
    use SyncsWithGMC;
    
    protected $fillable = [
        'name', 'description', 'price', 'stock', 'image_url', 
        'brand', 'sku', 'gmc_product_id', 'gmc_last_sync', 'status',
        'sync_enabled' // Optional: control sync per product
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

## Features

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

### Method 1: Using sync_enabled field
```php
// In your migration
$table->boolean('sync_enabled')->default(true);

// In your model
protected $fillable = ['sync_enabled', /* other fields */];

// Control sync per product
$product->sync_enabled = false;
$product->save(); // Won't sync to GMC even on status change

$product->sync_enabled = true;
$product->save(); // Will sync to GMC on status change to inactive
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

### Sync Status
```php
$status = $product->getGMCSyncStatus();
// Returns: ['is_synced' => true, 'gmc_id' => '123', 'last_sync' => '2024-01-01T00:00:00Z', 'sync_enabled' => true]
```

## Performance Optimizations

- **Batch processing** to handle large datasets efficiently
- **Rate limiting** to avoid API throttling
- **Cache protection** against duplicate syncs
- **Async processing** for better response times
- **Retry logic** for failed operations
- **Memory efficient** chunking for bulk operations 