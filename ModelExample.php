<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mannu24\GMCIntegration\Traits\SyncsWithGMC;

class Product extends Model
{
    use HasFactory, SyncsWithGMC;

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
            'additionalImageLinks' => [],

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
            'googleProductCategory' => 'product category name',

            // ℹ️ OPTIONAL: Your own categorization
            'productTypes' => [],

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
