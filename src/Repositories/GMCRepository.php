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
        try {
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
        } catch (\Exception $e) {
            Log::error('Failed to initialize Google API client', [
                'error' => $e->getMessage(),
                'file_path' => config('gmc.service_account_json'),
                'merchant_id' => config('gmc.merchant_id')
            ]);
            throw $e;
        }
    }

    public function uploadProduct(array $productData)
    {
        try {
            return $this->executeWithRetry(function () use ($productData) {
                $product = new \Google\Service\ShoppingContent\Product($productData);
                return $this->service->products->insert($this->merchantId, $product);
            }, 'upload product');
        } catch (\Exception $e) {
            Log::error('Failed to upload product to GMC', [
                'error' => $e->getMessage(),
                'offer_id' => $productData['offerId'] ?? 'unknown',
                'merchant_id' => $this->merchantId
            ]);
            throw $e;
        }
    }

    public function updateProduct(string $productId, array $productData)
    {
        try {
            return $this->executeWithRetry(function () use ($productId, $productData) {
                $product = new \Google\Service\ShoppingContent\Product($productData);
                return $this->service->products->insert($this->merchantId, $product);
            }, 'update product');
        } catch (\Exception $e) {
            Log::error('Failed to update product in GMC', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
                'merchant_id' => $this->merchantId
            ]);
            throw $e;
        }
    }

    public function deleteProduct(string $productId)
    {
        try {
            return $this->executeWithRetry(function () use ($productId) {
                $this->service->products->delete($this->merchantId, $productId);
                return true;
            }, 'delete product');
        } catch (\Exception $e) {
            Log::error('Failed to delete product from GMC', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
                'merchant_id' => $this->merchantId
            ]);
            throw $e;
        }
    }

    public function getProduct(string $productId)
    {
        try {
            return $this->executeWithRetry(function () use ($productId) {
                return $this->service->products->get($this->merchantId, $productId);
            }, 'get product');
        } catch (\Exception $e) {
            Log::error('Failed to get product from GMC', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
                'merchant_id' => $this->merchantId
            ]);
            throw $e;
        }
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
                
                Log::warning("GMC API attempt {$attempt} failed, retrying...", [
                    'operation' => $operationName,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_attempts' => $this->retryAttempts
                ]);
                
                $delay = $this->retryDelay * pow(2, $attempt - 1);
                usleep($delay * 1000);
            }
        }
        
        Log::error("GMC API operation failed after {$this->retryAttempts} attempts", [
            'operation' => $operationName,
            'error' => $lastException->getMessage(),
            'merchant_id' => $this->merchantId
        ]);
        
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
            Log::error('GMC API connection test failed', [
                'error' => $e->getMessage(),
                'merchant_id' => $this->merchantId
            ]);
            return false;
        }
    }
}
