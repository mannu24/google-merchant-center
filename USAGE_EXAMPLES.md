# Laravel GMC Integration - Usage Examples

## Basic Model Setup

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Manu\GMCIntegration\Traits\SyncsWithGMC;

class Product extends Model
{
    use SyncsWithGMC;
    
    protected $fillable = [
        'name', 'description', 'price', 'stock', 'image_url', 
        'brand', 'sku', 'gmc_product_id', 'gmc_last_sync', 'status'
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'gmc_last_sync' => 'datetime',
    ];

    // Required method - must return GMC-compatible data
    public function prepareGMCData(): array
    {
        return [
            'offerId' => $this->sku ?: (string) $this->id,
            'title' => $this->name,
            'description' => $this->description,
            'link' => url("/products/{$this->id}"),
            'imageLink' => $this->image_url,
            'price' => [
                'value' => (string) $this->price,
                'currency' => 'USD'
            ],
            'availability' => $this->stock > 0 ? 'in stock' : 'out of stock',
            'brand' => $this->brand,
            'condition' => 'new',
        ];
    }
}
```

## Manual Syncing

```php
// Laravel-style method (your preferred approach)
$product = Product::find(1);
$result = $product->syncwithgmc();

// Alternative method names
$product->syncToGMC();
$product->forceSyncToGMC(); // Ignores auto_sync_enabled setting

// Check sync status
if ($product->isSyncedWithGMC()) {
    $status = $product->getGMCSyncStatus();
    echo "Last synced: " . $status['last_sync'];
}
```

## Automatic Syncing

```php
// These will automatically sync to GMC (if auto_sync_enabled = true)
$product = Product::create([
    'name' => 'New Product',
    'price' => 29.99,
    'stock' => 100,
    'status' => 'active'
]);

$product->update(['price' => 24.99]); // Auto-syncs

$product->delete(); // Auto-removes from GMC
```

## Conditional Syncing

```php
class Product extends Model
{
    use SyncsWithGMC;
    
    // Only sync published products
    public function shouldSyncOnCreate(): bool
    {
        return $this->status === 'active';
    }
    
    // Only sync when important fields change
    public function shouldSyncOnUpdate(): bool
    {
        return $this->status === 'active' && 
               $this->isDirty(['name', 'price', 'stock', 'image_url']);
    }
    
    // Always allow deletion from GMC
    public function shouldDeleteFromGMC(): bool
    {
        return true;
    }
}
```

## Bulk Operations

```php
use Manu\GMCIntegration\Services\GMCService;

$gmcService = app(GMCService::class);

// Sync multiple products
$products = Product::where('status', 'active')->get();
$result = $gmcService->syncMultipleProducts($products);

echo "Synced: " . $result['successes'];
echo "Errors: " . count($result['errors']);
```

## Artisan Commands

```bash
# Sync all products of default model
php artisan gmc:sync-all

# Sync specific model
php artisan gmc:sync-all "App\\Models\\Product"

# Force sync (ignores last_sync timestamp)
php artisan gmc:sync-all --force

# Use smaller chunks for memory efficiency
php artisan gmc:sync-all --chunk=25
```

## Configuration

```env
# .env file
GMC_MERCHANT_ID=your_merchant_id_here
GMC_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
GMC_AUTO_SYNC=true
GMC_THROW_EXCEPTIONS=false
GMC_DEFAULT_MODEL="App\\Models\\Product"
```

## Advanced Features

### Multiple Images
```php
public function prepareGMCData(): array
{
    return [
        // ... other fields
        'imageLink' => $this->getMainImage(),
        'additionalImageLinks' => $this->getAdditionalImages(),
    ];
}

public function getAdditionalImages(): array
{
    return $this->images->pluck('url')->take(9)->toArray();
}
```

### Category Mapping
```php
public function prepareGMCData(): array
{
    return [
        // ... other fields
        'googleProductCategory' => $this->mapToGoogleCategory(),
        'productType' => $this->category->name,
    ];
}

private function mapToGoogleCategory(): string
{
    $mapping = [
        'electronics' => 'Electronics',
        'clothing' => 'Apparel & Accessories',
        'books' => 'Media > Books',
    ];
    
    return $mapping[$this->category->slug] ?? 'General';
}
```

### Error Handling
```php
// In controller
try {
    $product->syncwithgmc();
    return response()->json(['message' => 'Product synced successfully']);
} catch (\Exception $e) {
    Log::error('GMC Sync failed: ' . $e->getMessage());
    return response()->json(['error' => 'Sync failed'], 500);
}

// Or check return value
$success = $product->syncwithgmc();
if ($success) {
    // Handle success
} else {
    // Handle failure (error was logged)
}
``` 