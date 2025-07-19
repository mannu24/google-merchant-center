<?php

namespace Mannu24\GMCIntegration\Repositories;

use Mannu24\GMCIntegration\Repositories\Interfaces\ProductRepositoryInterface;
use Google\Client;
use Google\Service\ShoppingContent;
use Illuminate\Support\Facades\Log;

class ProductRepository implements ProductRepositoryInterface
{
    protected $merchantId;
    protected $service;

    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(config('gmc.service_account_json'));
        $client->addScope(ShoppingContent::CONTENT);
        
        $this->service = new ShoppingContent($client);
        $this->merchantId = config('gmc.merchant_id');
    }

    public function uploadProduct(array $productData)
    {
        try {
            $product = new ShoppingContent\Product($productData);
            $result = $this->service->products->insert($this->merchantId, $product);
            Log::info("Product uploaded to GMC", ['offer_id' => $productData['offerId'] ?? 'unknown']);
            return $result;
        } catch (\Exception $e) {
            Log::error("Product upload failed: " . $e->getMessage(), ['product_data' => $productData]);
            throw $e;
        }
    }

    public function updateProduct(string $productId, array $productData)
    {
        try {
            $product = new ShoppingContent\Product($productData);
            $result = $this->service->products->insert($this->merchantId, $product);
            Log::info("Product updated in GMC", ['product_id' => $productId]);
            return $result;
        } catch (\Exception $e) {
            Log::error("Product update failed: " . $e->getMessage(), ['product_id' => $productId]);
            throw $e;
        }
    }

    public function deleteProduct(string $productId)
    {
        try {
            $this->service->products->delete($this->merchantId, $productId);
            Log::info("Product deleted from GMC", ['product_id' => $productId]);
        } catch (\Exception $e) {
            Log::error("Product deletion failed: " . $e->getMessage(), ['product_id' => $productId]);
            throw $e;
        }
    }

    public function getProduct(string $productId)
    {
        try {
            return $this->service->products->get($this->merchantId, $productId);
        } catch (\Exception $e) {
            Log::error("Get product failed: " . $e->getMessage(), ['product_id' => $productId]);
            throw $e;
        }
    }
}
