<?php

namespace Mannu24\GMCIntegration\Repositories;

use Mannu24\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;
use Google\Client;
use Google\Service\ShoppingContent;
use Illuminate\Support\Facades\Log;

class GMCRepository implements GMCRepositoryInterface
{
    protected $service;
    protected $merchantId;
    protected $retryAttempts = 3;
    protected $retryDelay = 1000;

    public function __construct()
    {
        $this->initializeGoogleClient();
    }

    protected function initializeGoogleClient(): void
    {
        $client = new Client();
        $filePath = base_path(config('gmc.service_account_json'));
        
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Service account file not found: {$filePath}");
        }
        
        $client->setAuthConfig($filePath);
        $client->addScope(ShoppingContent::CONTENT);

        $this->service = new ShoppingContent($client);
        $this->merchantId = config('gmc.merchant_id');

        if (empty($this->merchantId)) {
            throw new \InvalidArgumentException('GMC merchant ID is not configured');
        }
    }

    public function uploadProduct(array $productData)
    {
        return $this->executeWithRetry(function () use ($productData) {
            $product = new \Google\Service\ShoppingContent\Product($productData);
            return $this->service->products->insert($this->merchantId, $product);
        }, 'upload product');
    }

    public function updateProduct(string $productId, array $productData)
    {
        return $this->executeWithRetry(function () use ($productId, $productData) {
            $product = new \Google\Service\ShoppingContent\Product($productData);
            return $this->service->products->insert($this->merchantId, $product);
        }, 'update product');
    }

    public function deleteProduct(string $productId)
    {
        return $this->executeWithRetry(function () use ($productId) {
            $this->service->products->delete($this->merchantId, $productId);
            return true;
        }, 'delete product');
    }

    public function getProduct(string $productId)
    {
        return $this->executeWithRetry(function () use ($productId) {
            return $this->service->products->get($this->merchantId, $productId);
        }, 'get product');
    }

    protected function executeWithRetry(callable $operation, string $operationName)
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                
                if ($this->shouldNotRetry($e) || $attempt === $this->retryAttempts) {
                    break;
                }
                
                $delay = $this->retryDelay * pow(2, $attempt - 1);
                usleep($delay * 1000);
            }
        }
        
        throw $lastException;
    }

    protected function shouldNotRetry(\Exception $e): bool
    {
        $nonRetryableErrors = [
            'invalid_grant', 'unauthorized_client', 'invalid_client',
            'invalid_request', 'access_denied'
        ];
        
        $message = strtolower($e->getMessage());
        
        foreach ($nonRetryableErrors as $error) {
            if (strpos($message, $error) !== false) {
                return true;
            }
        }
        
        return false;
    }

    public function setRetryAttempts(int $attempts): self
    {
        $this->retryAttempts = $attempts;
        return $this;
    }

    public function setRetryDelay(int $delay): self
    {
        $this->retryDelay = $delay;
        return $this;
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function testConnection(): bool
    {
        try {
            $account = $this->service->accounts->get($this->merchantId);
            return !empty($account->id);
        } catch (\Exception $e) {
            return false;
        }
    }
}
