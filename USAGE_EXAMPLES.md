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
        'title', 'description', 'price', 'special_price', 'special_price_from', 'special_price_to',
        'quantity', 'image_url', 'additional_images', 'brand', 'sku', 'gtin', 'mpn', 'url_key',
        'weight', 'length', 'width', 'height', 'color', 'sizes', 'material', 'pattern',
        'parent_sku', 'pack_size', 'is_bundle', 'status'
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'special_price' => 'decimal:2',
        'quantity' => 'integer',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_bundle' => 'boolean',
        'additional_images' => 'array',
        'sizes' => 'array',
    ];

    // Required method - must return GMC-compatible data
    public function prepareGMCData(): array
    {
        return [
            // ✅ REQUIRED: Merchant unique product ID
            'offerId' => $this->sku ?: (string) $this->id,

            // ✅ REQUIRED: Product title (max 150 chars recommended)
            'title' => $this->title,

            // ✅ REQUIRED: Product description (max 5000 chars)
            'description' => $this->description,

            // ✅ REQUIRED: Link to your product landing page
            'link' => url("/product/{$this->url_key}"),

            // ✅ REQUIRED: Main product image
            'imageLink' => 'main image link',

            // ℹ️ OPTIONAL: Up to 10 additional product images
            'additionalImageLinks' => [], // Array of image links

            // ✅ REQUIRED: Price object
            'price' => [
                'value' => (string) $this->price,
                'currency' => 'INR',
            ],

            // ✅ REQUIRED: Product availability ("in stock", "out of stock", "preorder")
            'availability' => $this->quantity > 0 ? 'in stock' : 'out of stock',

            // ✅ REQUIRED: Condition of the product ("new", "refurbished", "used")
            'condition' => 'new',

            // ✅ REQUIRED: Content language (ISO 639-1 code)
            'contentLanguage' => 'en',

            // ✅ REQUIRED: Target country (ISO 3166 country code)
            'targetCountry' => 'IN',

            // ✅ REQUIRED: Sales channel ("online" or "local")
            'channel' => 'online',

            // ✅ REQUIRED if GTIN exists: Global Trade Item Number
            'gtin' => $this->gtin ?? null,

            // ℹ️ OPTIONAL but recommended if no GTIN: Manufacturer Part Number
            'mpn' => $this->mpn,

            // ℹ️ OPTIONAL: Brand name (required for non-apparel in many countries)
            'brand' => $this->brand ?? 'Your Brand Name',

            // ℹ️ OPTIONAL: Google-defined product category (numeric ID preferred, string accepted)
            // Example numeric ID: "Apparel & Accessories > Clothing" = 1604
            'googleProductCategory' => 'product category name',

            // ℹ️ OPTIONAL: Your own categorization
            'productTypes' => [], // Array of product types

            // ℹ️ OPTIONAL: Sale price if product is discounted
            'salePrice' => ['value' => '0', 'currency' => 'INR'],

            // ℹ️ OPTIONAL: Effective sale period
            'salePriceEffectiveDate' => 'sale price effective date',

            // ℹ️ OPTIONAL: Shipping weight
            'shippingWeight' => $this->weight
                ? ['value' => (string) $this->weight, 'unit' => 'kg']
                : null,

            // ℹ️ OPTIONAL: Product dimensions
            'shippingLength' => $this->length
                ? ['value' => (string) $this->length, 'unit' => 'cm']
                : null,
            'shippingWidth' => $this->width
                ? ['value' => (string) $this->width, 'unit' => 'cm']
                : null,
            'shippingHeight' => $this->height
                ? ['value' => (string) $this->height, 'unit' => 'cm']
                : null,

            // ℹ️ OPTIONAL: Shipping cost (if you want to override GMC settings)
            'shipping' => [[
                'country' => 'IN',
                'service' => 'Standard',
                'price' => ['value' => '0', 'currency' => 'INR']
            ]],

            // ℹ️ OPTIONAL: Age group ("newborn", "infant", "toddler", "kids", "adult")
            'ageGroup' => 'adult',

            // ℹ️ OPTIONAL: Gender ("male", "female", "unisex")
            'gender' => 'unisex',

            // ℹ️ OPTIONAL: Color, Size (important for apparel)
            'color' => $this->color ?? null,
            'sizes' => $this->sizes ?? null,

            // ℹ️ OPTIONAL: Material, Pattern
            'material' => $this->material ?? null,
            'pattern' => $this->pattern ?? null,

            // ℹ️ OPTIONAL: Item group ID (for product variants like color/size)
            'itemGroupId' => $this->parent_sku ?? null,

            // ℹ️ OPTIONAL: Energy efficiency label (electronics, appliances)
            'energyEfficiencyClass' => null,

            // ℹ️ OPTIONAL: Multipack (if product is sold in packs >1)
            'multipack' => $this->pack_size ?? null,

            // ℹ️ OPTIONAL: Bundle flag (true if product is a bundle)
            'isBundle' => $this->is_bundle ?? false,
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
    'title' => 'New Product',
    'price' => 29.99,
    'quantity' => 100,
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
               $this->isDirty(['title', 'price', 'quantity', 'image_url']);
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
        'imageLink' => $this->image_url ?? 'main image url',
        'additionalImageLinks' => $this->additional_images ?? [],
    ];
}
```

### Category Mapping
```php
public function prepareGMCData(): array
{
    return [
        // ... other fields
        'googleProductCategory' => $this->category ?? 'General',
        'productTypes' => [$this->category, $this->subcategory, $this->brand],
    ];
}
```

### Sale Price and Discounts
```php
public function prepareGMCData(): array
{
    return [
        // ... other fields
        'salePrice' => $this->special_price
            ? ['value' => (string) $this->special_price, 'currency' => 'INR']
            : null,
        'salePriceEffectiveDate' => $this->special_price_from && $this->special_price_to
            ? "{$this->special_price_from}/{$this->special_price_to}"
            : null,
    ];
}
```

### Shipping and Dimensions
```php
public function prepareGMCData(): array
{
    return [
        // ... other fields
        'shippingWeight' => $this->weight
            ? ['value' => (string) $this->weight, 'unit' => 'kg']
            : null,
        'shippingLength' => $this->length
            ? ['value' => (string) $this->length, 'unit' => 'cm']
            : null,
        'shipping' => [[
            'country' => 'IN',
            'service' => 'Standard',
            'price' => ['value' => '0', 'currency' => 'INR']
        ]],
    ];
}
```

### Product Variants
```php
public function prepareGMCData(): array
{
    return [
        // ... other fields
        'itemGroupId' => $this->parent_sku ?? null,
        'color' => $this->color ?? null,
        'sizes' => $this->sizes ?? null,
        'material' => $this->material ?? null,
        'pattern' => $this->pattern ?? null,
    ];
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