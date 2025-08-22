<?php

namespace Mannu24\GMCIntegration\Services;

use Mannu24\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class GMCService
{
    protected $gmcRepository;
    protected $batchSize = 50;

    public function __construct(GMCRepositoryInterface $gmcRepository)
    {
        $this->gmcRepository = $gmcRepository;
    }

    public function syncProduct($model)
    {
        try {
            $gmcData = $this->prepareProductData($model);
            $this->validateProductData($gmcData);
            
            $existingGmcId = $model->getGMCId();
            
            if ($existingGmcId) {
                return $this->gmcRepository->updateProduct($existingGmcId, $gmcData);
            } else {
                return $this->gmcRepository->uploadProduct($gmcData);
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync product", [
                'model_id' => $model->getKey(),
                'error' => $e->getMessage()
            ]);
            
            if ($this->shouldThrowExceptions()) {
                throw $e;
            }
            
            return false;
        }
    }

    public function syncMultipleProducts(Collection $models, int $batchSize = null)
    {
        $batchSize = $batchSize ?? $this->batchSize;
        $results = [];
        $errors = [];
        $total = $models->count();

        $models->chunk($batchSize)->each(function ($batch, $batchIndex) use (&$results, &$errors) {
            foreach ($batch as $model) {
                try {
                    $result = $this->syncProduct($model);
                    if ($result) {
                        $results[] = $result;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'model_id' => $model->getKey(),
                        'error' => $e->getMessage()
                    ];
                    
                    // If exceptions should be thrown, throw the first one
                    if ($this->shouldThrowExceptions()) {
                        throw $e;
                    }
                }
            }
            
            if ($batchIndex > 0) {
                usleep(100000);
            }
        });

        // If we have errors and exceptions should be thrown, throw the first error
        if (!empty($errors) && $this->shouldThrowExceptions()) {
            $firstError = $errors[0];
            throw new \Exception("GMC sync failed for product {$firstError['model_id']}: {$firstError['error']}");
        }

        return [
            'successes' => count($results),
            'errors' => $errors,
            'total' => $total
        ];
    }

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

    public function deleteProduct(string $productId)
    {
        try {
            return $this->gmcRepository->deleteProduct($productId);
        } catch (\Exception $e) {
            Log::error("Failed to delete product from GMC", [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            if ($this->shouldThrowExceptions()) {
                throw $e;
            }
            
            return false;
        }
    }

    public function getProduct(string $productId)
    {
        try {
            return $this->gmcRepository->getProduct($productId);
        } catch (\Exception $e) {
            Log::error("Failed to get product from GMC", [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            if ($this->shouldThrowExceptions()) {
                throw $e;
            }
            
            return false;
        }
    }

    protected function prepareProductData($model): array
    {
        $data = $model->prepareGMCData();
        return $this->ensureRequiredFields($data);
    }

    protected function ensureRequiredFields(array $data): array
    {
        $defaults = [
            'condition' => 'new',
            'availability' => 'in stock',
            'price' => ['value' => '0.00', 'currency' => 'USD'],
        ];

        return array_merge($defaults, $data);
    }

    public function validateProductData(array $productData): bool
    {
        $required = ['offerId', 'title', 'description', 'link', 'imageLink', 'price', 'availability'];
        
        foreach ($required as $field) {
            if (!isset($productData[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!isset($productData['price']['value']) || !isset($productData['price']['currency'])) {
            throw new \InvalidArgumentException("Price must have 'value' and 'currency' fields");
        }

        if (!is_numeric($productData['price']['value']) || $productData['price']['value'] < 0) {
            throw new \InvalidArgumentException("Price value must be a positive number");
        }

        $validAvailabilities = ['in stock', 'out of stock', 'preorder'];
        if (!in_array($productData['availability'], $validAvailabilities)) {
            throw new \InvalidArgumentException("Invalid availability value. Must be one of: " . implode(', ', $validAvailabilities));
        }

        if (isset($productData['condition'])) {
            $validConditions = ['new', 'used', 'refurbished'];
            if (!in_array($productData['condition'], $validConditions)) {
                throw new \InvalidArgumentException("Invalid condition value. Must be one of: " . implode(', ', $validConditions));
            }
        }

        return true;
    }

    protected function shouldThrowExceptions(): bool
    {
        return Config::get('gmc.throw_sync_exceptions', true);
    }

    public function setBatchSize(int $batchSize): self
    {
        $this->batchSize = $batchSize;
        return $this;
    }
}
