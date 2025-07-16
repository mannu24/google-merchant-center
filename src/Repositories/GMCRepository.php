<?php

namespace Manu\GMCIntegration\Repositories;

use Manu\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;
use Google\Client;
use Google\Service\ShoppingContent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GMCRepository implements GMCRepositoryInterface
{
    protected $service;
    protected $merchantId;
    protected $retryAttempts = 3;
    protected $retryDelay = 1000; // milliseconds

    public function __construct()
    {
        $this->initializeGoogleClient();
    }

    /**
     * Initialize Google API client
     */
    protected function initializeGoogleClient(): void
    {
        try {
            $client = new Client();
            $client->setAuthConfig(config('gmc.service_account_json'));
            $client->addScope(ShoppingContent::CONTENT);

            $this->service = new ShoppingContent($client);
            $this->merchantId = config('gmc.merchant_id');

            if (empty($this->merchantId)) {
                throw new \InvalidArgumentException('GMC merchant ID is not configured');
            }
        } catch (\Exception $e) {
            Log::error('Failed to initialize Google API client', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Upload product to GMC with retry logic
     */
    public function uploadProduct(array $productData)
    {
        return $this->executeWithRetry(function () use ($productData) {
            $product = new \Google\Service\ShoppingContent\Product($productData);
            $result = $this->service->products->insert($this->merchantId, $product);
            
            Log::info("Product uploaded to GMC", [
                'offer_id' => $productData['offerId'] ?? 'unknown',
                'gmc_id' => $result->id ?? null
            ]);
            
            return $result;
        }, 'upload product');
    }

    /**
     * Update product in GMC
     */
    public function updateProduct(string $productId, array $productData)
    {
        return $this->executeWithRetry(function () use ($productId, $productData) {
            $product = new \Google\Service\ShoppingContent\Product($productData);
            $result = $this->service->products->insert($this->merchantId, $product);
            
            Log::info("Product updated in GMC", [
                'product_id' => $productId,
                'gmc_id' => $result->id ?? null
            ]);
            
            return $result;
        }, 'update product');
    }

    /**
     * Delete product from GMC
     */
    public function deleteProduct(string $productId)
    {
        return $this->executeWithRetry(function () use ($productId) {
            $this->service->products->delete($this->merchantId, $productId);
            
            Log::info("Product deleted from GMC", [
                'product_id' => $productId
            ]);
            
            return true;
        }, 'delete product');
    }

    /**
     * Get product from GMC
     */
    public function getProduct(string $productId)
    {
        return $this->executeWithRetry(function () use ($productId) {
            $result = $this->service->products->get($this->merchantId, $productId);
            
            Log::info("Product retrieved from GMC", [
                'product_id' => $productId
            ]);
            
            return $result;
        }, 'get product');
    }

    /**
     * Execute operation with retry logic
     */
    protected function executeWithRetry(callable $operation, string $operationName)
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                
                Log::warning("GMC API attempt {$attempt} failed", [
                    'operation' => $operationName,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_attempts' => $this->retryAttempts
                ]);
                
                // Don't retry on certain errors
                if ($this->shouldNotRetry($e)) {
                    break;
                }
                
                // Wait before retry (exponential backoff)
                if ($attempt < $this->retryAttempts) {
                    $delay = $this->retryDelay * pow(2, $attempt - 1);
                    usleep($delay * 1000); // Convert to microseconds
                }
            }
        }
        
        Log::error("GMC API operation failed after {$this->retryAttempts} attempts", [
            'operation' => $operationName,
            'error' => $lastException->getMessage()
        ]);
        
        throw $lastException;
    }

    /**
     * Determine if error should not be retried
     */
    protected function shouldNotRetry(\Exception $e): bool
    {
        $nonRetryableErrors = [
            'invalid_grant',
            'unauthorized_client',
            'invalid_client',
            'invalid_request',
            'access_denied'
        ];
        
        $message = strtolower($e->getMessage());
        
        foreach ($nonRetryableErrors as $error) {
            if (strpos($message, $error) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Set retry attempts
     */
    public function setRetryAttempts(int $attempts): self
    {
        $this->retryAttempts = $attempts;
        return $this;
    }

    /**
     * Set retry delay
     */
    public function setRetryDelay(int $delay): self
    {
        $this->retryDelay = $delay;
        return $this;
    }

    /**
     * Get merchant ID
     */
    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    /**
     * Test API connection
     */
    public function testConnection(): bool
    {
        try {
            // Try to get account info to test connection
            $account = $this->service->accounts->get($this->merchantId);
            return !empty($account->id);
        } catch (\Exception $e) {
            Log::error("GMC API connection test failed", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
