<?php

namespace Manu\GMCIntegration\Repositories;

use Manu\GMCIntegration\Repositories\Interfaces\ProductRepositoryInterface;
use Google\Client;
use Google\Service\ShoppingContent;

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
        $product = new ShoppingContent\Product($productData);

        return $this->service->products->insert($this->merchantId, $product);
    }
}
