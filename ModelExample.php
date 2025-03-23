<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Manu\GMCIntegration\Traits\SyncsWithGMC;

class ModelExample extends Model
{
    use HasFactory, SyncsWithGMC;

    protected $fillable = ['name', 'description', 'price', 'image_url', 'stock', 'brand'];

    public function getGMCData(): array
    {
        return [
            'offerId' => (string) $this->id,
            'title' => $this->name,
            'description' => $this->description,
            'link' => route('product.show', $this->id),
            'imageLink' => $this->getGMCImage(),
            'price' => ['value' => $this->price, 'currency' => 'USD'],
            'availability' => $this->stock > 0 ? 'in stock' : 'out of stock',
            'brand' => $this->brand,
            'condition' => 'new'
        ];
    }

    public function getGMCImage()
    {
        return $this->image_url ?? 'https://example.com/default-product-image.jpg';
    }
}
