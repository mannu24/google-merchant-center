<?php

namespace Mannu24\GMCIntegration\Services;

use Mannu24\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GMCService
{
    protected $gmcRepository;
    protected $batchSize = 50;
    protected $retryAttempts = 3;

    public function __construct(GMCRepositoryInterface $gmcRepository)
    {
        $this->gmcRepository = $gmcRepository;
    }

    /**
     * Sync a single product to GMC
     */
    public function syncProduct($model)
    {
        $cacheKey = "gmc_sync_product_{$model->getTable()}_{$model->getKey()}";
        
        // Check cache to prevent duplicate syncs
        if (Cache::has($cacheKey)) {
            Log::info("Skipping duplicate sync for product {$model->getKey()}");
            return false;
        }
        
        Cache::put($cacheKey, true, now()->addMinutes(2));
        
        try {
            $gmcData = $this->prepareProductData($model);
            $this->validateProductData($gmcData);
            
            // Check if product already exists in GMC
            $existingGmcId = $model->getGMCId();
            
            if ($existingGmcId) {
                // Update existing product
                $result = $this->gmcRepository->updateProduct($existingGmcId, $gmcData);
                Log::info("Product updated in GMC", [
                    'model_id' => $model->getKey(),
                    'table' => $model->getTable(),
                    'gmc_id' => $existingGmcId
                ]);
            } else {
                // Create new product
                $result = $this->gmcRepository->uploadProduct($gmcData);
                Log::info("Product created in GMC", [
                    'model_id' => $model->getKey(),
                    'table' => $model->getTable(),
                    'gmc_id' => $result->id ?? null
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Failed to sync product", [
                'model_id' => $model->getKey(),
                'table' => $model->getTable(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            Cache::forget($cacheKey);
        }
    }

    /**
     * Sync multiple products with batch processing
     */
    public function syncMultipleProducts(Collection $models, int $batchSize = null)
    {
        $batchSize = $batchSize ?? $this->batchSize;
        $results = [];
        $errors = [];
        $total = $models->count();
        
        Log::info("Starting bulk sync", [
            'total_products' => $total,
            'batch_size' => $batchSize
        ]);

        $models->chunk($batchSize)->each(function ($batch, $batchIndex) use (&$results, &$errors) {
            Log::info("Processing batch {$batchIndex}", ['batch_size' => $batch->count()]);
            
            foreach ($batch as $model) {
                try {
                    $result = $this->syncProduct($model);
                    if ($result) {
                        $results[] = $result;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'model_id' => $model->getKey(),
                        'table' => $model->getTable(),
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Small delay between batches to avoid rate limiting
            if ($batchIndex > 0) {
                usleep(100000); // 0.1 second
            }
        });

        $successCount = count($results);
        $errorCount = count($errors);
        
        Log::info("Bulk sync completed", [
            'successful' => $successCount,
            'errors' => $errorCount,
            'total' => $total
        ]);

        if (!empty($errors)) {
            Log::warning("Bulk sync completed with errors", ['errors' => $errors]);
        }

        return [
            'successes' => $successCount,
            'errors' => $errors,
            'total' => $total
        ];
    }

    /**
     * Force update an existing product in GMC
     */
    public function forceUpdateProduct($model)
    {
        $gmcId = $model->getGMCId();
        
        if (!$gmcId) {
            throw new \InvalidArgumentException("Product is not yet synced with GMC");
        }
        
        $gmcData = $this->prepareProductData($model);
        $this->validateProductData($gmcData);
        
        return $this->gmcRepository->updateProduct($gmcId, $gmcData);
    }

    /**
     * Delete product from GMC
     */
    public function deleteProduct(string $productId)
    {
        try {
            $result = $this->gmcRepository->deleteProduct($productId);
            
            Log::info("Product deleted from GMC", [
                'product_id' => $productId
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Failed to delete product from GMC", [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get product from GMC
     */
    public function getProduct(string $productId)
    {
        try {
            return $this->gmcRepository->getProduct($productId);
        } catch (\Exception $e) {
            Log::error("Failed to get product from GMC", [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Prepare product data with validation
     */
    protected function prepareProductData($model): array
    {
        $data = $model->prepareGMCData();
        
        // Ensure required fields are present
        $data = $this->ensureRequiredFields($data);
        
        // Sanitize data
        $data = $this->sanitizeProductData($data);
        
        return $data;
    }

    /**
     * Ensure all required fields are present
     */
    protected function ensureRequiredFields(array $data): array
    {
        $defaults = [
            'condition' => 'new',
            'availability' => 'in stock',
            'price' => ['value' => '0.00', 'currency' => 'USD'],
        ];

        return array_merge($defaults, $data);
    }

    /**
     * Sanitize product data
     */
    protected function sanitizeProductData(array $data): array
    {
        // Clean strings
        foreach (['title', 'description', 'brand'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim(strip_tags($data[$field]));
            }
        }

        // Ensure price is properly formatted
        if (isset($data['price']['value'])) {
            $data['price']['value'] = number_format((float) $data['price']['value'], 2, '.', '');
        }

        // Ensure offerId is string
        if (isset($data['offerId'])) {
            $data['offerId'] = (string) $data['offerId'];
        }

        return $data;
    }

    /**
     * Validate product data before syncing
     */
    public function validateProductData(array $productData): bool
    {
        $required = ['offerId', 'title', 'description', 'link', 'imageLink', 'price', 'availability'];
        
        foreach ($required as $field) {
            if (!isset($productData[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate price format
        if (!isset($productData['price']['value']) || !isset($productData['price']['currency'])) {
            throw new \InvalidArgumentException("Price must have 'value' and 'currency' fields");
        }

        // Validate price value
        if (!is_numeric($productData['price']['value']) || $productData['price']['value'] < 0) {
            throw new \InvalidArgumentException("Price value must be a positive number");
        }

        // Validate availability
        $validAvailabilities = ['in stock', 'out of stock', 'preorder'];
        if (!in_array($productData['availability'], $validAvailabilities)) {
            throw new \InvalidArgumentException("Invalid availability value. Must be one of: " . implode(', ', $validAvailabilities));
        }

        // Validate condition
        if (isset($productData['condition'])) {
            $validConditions = ['new', 'used', 'refurbished'];
            if (!in_array($productData['condition'], $validConditions)) {
                throw new \InvalidArgumentException("Invalid condition value. Must be one of: " . implode(', ', $validConditions));
            }
        }

        return true;
    }

    /**
     * Set batch size for bulk operations
     */
    public function setBatchSize(int $batchSize): self
    {
        $this->batchSize = $batchSize;
        return $this;
    }

    /**
     * Set retry attempts for failed operations
     */
    public function setRetryAttempts(int $attempts): self
    {
        $this->retryAttempts = $attempts;
        return $this;
    }
}
