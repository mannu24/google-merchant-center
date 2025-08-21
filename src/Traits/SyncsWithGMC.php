<?php

namespace Mannu24\GMCIntegration\Traits;

use Mannu24\GMCIntegration\Services\GMCService;
use Mannu24\GMCIntegration\Models\GMCProduct;
use Mannu24\GMCIntegration\Models\GMCSyncLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;

trait SyncsWithGMC
{
    /**
     * Boot the trait and register model events
     */
    public static function bootSyncsWithGMC()
    {
        // Enable automatic sync on create
        static::created(function ($model) {
            if (!$model->shouldSyncToGMC()) {
                return;
            }
            
            if (Config::get('gmc.auto_sync_enabled', true)) {
                dispatch(function () use ($model) {
                    $model->syncToGMC();
                })->afterResponse();
            }
        });

        // Enable automatic sync on update
        static::updated(function ($model) {
            if (!$model->shouldSyncToGMC()) {
                return;
            }
            
            // Sync on any update, not just status change
            if (Config::get('gmc.auto_sync_enabled', true)) {
                dispatch(function () use ($model) {
                    $model->syncToGMC();
                })->afterResponse();
            }
        });

        // Keep automatic sync on delete
        static::deleted(function ($model) {
            if (!$model->shouldSyncToGMC()) {
                return;
            }
            
            if (method_exists($model, 'shouldDeleteFromGMC') && !$model->shouldDeleteFromGMC()) {
                return;
            }
            
            if (Config::get('gmc.auto_sync_enabled', true)) {
                dispatch(function () use ($model) {
                    $model->deleteFromGMC();
                })->afterResponse();
            }
        });
    }

    /**
     * Get or create GMC product record
     */
    public function getGMCProduct(): ?GMCProduct
    {
        return GMCProduct::where('product_id', $this->getKey())
            ->where('product_type', get_class($this))
            ->first();
    }

    /**
     * Create or get GMC product record
     */
    public function createGMCProduct(): GMCProduct
    {
        return GMCProduct::firstOrCreate([
            'product_id' => $this->getKey(),
            'product_type' => get_class($this)
        ], [
            'sync_enabled' => true,
            'sync_status' => 'pending'
        ]);
    }

    /**
     * Sync product to Google Merchant Center
     */
    public function syncToGMC()
    {
        $cacheKey = "gmc_sync_{$this->getTable()}_{$this->getKey()}";
        
        // Prevent duplicate syncs within 5 minutes
        if (Cache::has($cacheKey)) {
            Log::info("Skipping duplicate sync for {$this->getTable()} ID {$this->getKey()}");
            return false;
        }
        
        Cache::put($cacheKey, true, Carbon::now()->addMinutes(5));
        
        try {
            $gmcProduct = $this->createGMCProduct();
            
            if (!$gmcProduct->isSyncEnabled()) {
                Log::info("Sync disabled for product {$this->getKey()}");
                return false;
            }
            
            $gmcProduct->updateSyncStatus('pending');
            
            $gmcService = app(GMCService::class);
            $result = $gmcService->syncProduct($this);
            
            // Update sync timestamp and GMC ID
            $this->updateGMCData($result);
            
            Log::info("Product {$this->getKey()} successfully synced to GMC", [
                'table' => $this->getTable(),
                'gmc_id' => $result->id ?? null
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $gmcProduct = $this->getGMCProduct();
            if ($gmcProduct) {
                $gmcProduct->markAsFailed($e->getMessage());
            }
            
            Log::error("Failed to sync product {$this->getKey()} to GMC", [
                'table' => $this->getTable(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (Config::get('gmc.throw_sync_exceptions', true)) {
                throw $e;
            }
            
            return false;
        } finally {
            Cache::forget($cacheKey);
        }
    }

    /**
     * Laravel-style method to sync current model instance with GMC
     */
    public function syncwithgmc()
    {
        return $this->syncToGMC();
    }

    /**
     * Force sync regardless of auto_sync setting
     */
    public function forceSyncToGMC()
    {
        return $this->syncToGMC();
    }

    /**
     * Force update existing product in GMC (must already be synced)
     */
    public function forceUpdateInGMC()
    {
        try {
            $gmcService = app(GMCService::class);
            return $gmcService->forceUpdateProduct($this);
        } catch (\Exception $e) {
            Log::error("Failed to force update product {$this->getKey()} in GMC", [
                'table' => $this->getTable(),
                'error' => $e->getMessage()
            ]);
            
            if (Config::get('gmc.throw_sync_exceptions', true)) {
                throw $e;
            }
            
            return false;
        }
    }

    /**
     * Delete product from Google Merchant Center
     */
    public function deleteFromGMC()
    {
        try {
            $gmcService = app(GMCService::class);
            $productId = $this->getGMCId();
            
            if (!$productId) {
                Log::warning("No GMC product ID found for model {$this->getKey()}", [
                    'table' => $this->getTable()
                ]);
                return false;
            }
            
            $gmcService->deleteProduct($productId);
            
            // Clear GMC data after successful deletion
            $this->clearGMCData();
            
            Log::info("Product {$this->getKey()} successfully deleted from GMC", [
                'table' => $this->getTable(),
                'gmc_id' => $productId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete product {$this->getKey()} in GMC", [
                'table' => $this->getTable(),
                'error' => $e->getMessage()
            ]);
            
            if (Config::get('gmc.throw_sync_exceptions', true)) {
                throw $e;
            }
            
            return false;
        }
    }

    /**
     * Check if product is synced with GMC
     */
    public function isSyncedWithGMC(): bool
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->isSynced() : false;
    }

    /**
     * Get the sync status
     */
    public function getGMCSyncStatus(): array
    {
        $gmcProduct = $this->getGMCProduct();
        
        return [
            'is_synced' => $gmcProduct ? $gmcProduct->isSynced() : false,
            'gmc_id' => $gmcProduct ? $gmcProduct->gmc_product_id : null,
            'last_sync' => $gmcProduct ? $gmcProduct->gmc_last_sync?->toISOString() : null,
            'sync_enabled' => $this->shouldSyncToGMC(),
            'sync_status' => $gmcProduct ? $gmcProduct->sync_status : 'pending',
            'last_error' => $gmcProduct ? $gmcProduct->last_error : null,
        ];
    }

    /**
     * Check if this specific product should sync to GMC
     * Override this method to make syncing optional per product
     */
    public function shouldSyncToGMC(): bool
    {
        $gmcProduct = $this->getGMCProduct();
        
        if ($gmcProduct) {
            return $gmcProduct->isSyncEnabled();
        }
        
        // Default to true if no GMC product record exists
        return true;
    }

    /**
     * Enable sync for this product
     */
    public function enableGMCSync(): void
    {
        $gmcProduct = $this->createGMCProduct();
        $gmcProduct->update(['sync_enabled' => true]);
    }

    /**
     * Disable sync for this product
     */
    public function disableGMCSync(): void
    {
        $gmcProduct = $this->getGMCProduct();
        if ($gmcProduct) {
            $gmcProduct->update(['sync_enabled' => false]);
        }
    }

    /**
     * Get sync logs for this product
     */
    public function getGMCSyncLogs()
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->syncLogs() : collect();
    }

    /**
     * Get last successful sync
     */
    public function getLastSuccessfulGMCSync()
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->getLastSuccessfulSync() : null;
    }

    /**
     * Get last error
     */
    public function getLastGMCError()
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->getLastError() : null;
    }

    /**
     * Update GMC data after successful sync
     */
    protected function updateGMCData($result): void
    {
        $gmcProduct = $this->getGMCProduct();
        if ($gmcProduct) {
            $gmcProduct->markAsSynced(
                $result->id ?? null,
                $this->prepareGMCData()
            );
        }
    }

    /**
     * Clear GMC data after deletion
     */
    protected function clearGMCData(): void
    {
        $gmcProduct = $this->getGMCProduct();
        if ($gmcProduct) {
            $gmcProduct->update([
                'gmc_product_id' => null,
                'gmc_last_sync' => null,
                'sync_status' => 'pending'
            ]);
        }
    }

    // Required method that models must implement
    abstract public function prepareGMCData(): array;

    /**
     * Get GMC product ID
     */
    public function getGMCId(): ?string
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->gmc_product_id : null;
    }

    /**
     * Get last sync timestamp
     */
    public function getGMCLastSync(): ?string
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->gmc_last_sync?->toISOString() : null;
    }

    // Optional methods for conditional syncing
    public function shouldSyncOnCreate(): bool
    {
        return true;
    }

    public function shouldSyncOnUpdate(): bool
    {
        return true;
    }

    public function shouldDeleteFromGMC(): bool
    {
        return true;
    }
}
