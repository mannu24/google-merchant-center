<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Manu\GMCIntegration\Traits\SyncsWithGMC;

class Product extends Model
{
    use HasFactory, SyncsWithGMC;

    protected $fillable = [
        'name', 
        'description', 
        'price', 
        'image_url', 
        'stock', 
        'brand', 
        'sku',
        'status'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    /**
     * Required method for GMC integration
     * This method must return data in Google Merchant Center format
     */
    public function prepareGMCData(): array
    {
        return [
            'offerId' => $this->sku ?: (string) $this->id,
            'title' => $this->name,
            'description' => $this->description,
            'link' => url("/products/{$this->id}"),
            'imageLink' => $this->getMainImage(),
            'additionalImageLinks' => array_slice($this->prepareImages(), 1, 9), // Max 10 additional images
            'price' => [
                'value' => (string) $this->price,
                'currency' => 'USD'
            ],
            'availability' => $this->stock > 0 ? 'in stock' : 'out of stock',
            'brand' => $this->brand,
            'condition' => 'new',
            'gtin' => $this->gtin ?? null,
            'mpn' => $this->sku, // Manufacturer Part Number
            'googleProductCategory' => $this->getGoogleProductCategory(),
            'productType' => $this->category ?? 'General',
        ];
    }

    /**
     * Get all product images as array
     */
    public function prepareImages(): array
    {
        $images = [];
        
        if ($this->image_url) {
            $mainImage = str_starts_with($this->image_url, 'http') 
                ? $this->image_url 
                : url($this->image_url);
            
            $images[] = $mainImage;
        } else {
            $images[] = 'https://via.placeholder.com/300x300.png?text=No+Image';
        }
        
        // Add additional images if available
        if (method_exists($this, 'getAdditionalImages')) {
            $additionalImages = $this->getAdditionalImages();
            if (is_array($additionalImages)) {
                $images = array_merge($images, $additionalImages);
            }
        }
        
        return $images;
    }
    
    /**
     * Get the main product image URL for GMC
     */
    public function getMainImage(): string
    {
        $images = $this->prepareImages();
        return $images[0] ?? 'https://via.placeholder.com/300x300.png?text=No+Image';
    }

    /**
     * Get Google Product Category (you should map your categories to Google's taxonomy)
     */
    public function getGoogleProductCategory(): ?string
    {
        // Example mapping - customize based on your categories
        $categoryMapping = [
            'electronics' => 'Electronics',
            'clothing' => 'Apparel & Accessories',
            'books' => 'Media > Books',
            // Add more mappings as needed
        ];

        return $categoryMapping[$this->category] ?? null;
    }

    /**
     * Example of additional images method (customize as needed)
     */
    public function getAdditionalImages(): array
    {
        // If you have a separate images table/relationship
        // return $this->images->pluck('url')->toArray();
        
        // Or if stored as JSON
        // return json_decode($this->additional_images, true) ?? [];
        
        return [];
    }
}
