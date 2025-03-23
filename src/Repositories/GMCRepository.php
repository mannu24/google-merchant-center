<?php

namespace Manu\GMCIntegration\Repositories;

use Manu\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;
use Google\Client;
use Google\Service\ShoppingContent;
use Illuminate\Support\Facades\Log;

class GMCRepository implements GMCRepositoryInterface
{
    protected $service;
    protected $merchantId;

    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(storage_path('gmc-service-account.json'));
        $client->addScope(ShoppingContent::CONTENT);

        $this->service = new ShoppingContent($client);
        $this->merchantId = env('GMC_MERCHANT_ID');
    }

    public function uploadProduct(array $productData)
    {
        try {
            $this->service->products->insert($this->merchantId, new \Google\Service\ShoppingContent\Product($productData));
            Log::info("Product synced to GMC: " . json_encode($productData));
        } catch (\Exception $e) {
            Log::error("GMC Sync Error: " . $e->getMessage());
        }
    }

    public function deleteProduct(string $productId)
    {
        try {
            $this->service->products->delete($this->merchantId, $productId);
            Log::info("Product deleted from GMC: " . $productId);
        } catch (\Exception $e) {
            Log::error("GMC Delete Error: " . $e->getMessage());
        }
    }
}
