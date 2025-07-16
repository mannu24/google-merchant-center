<?php

namespace Manu\GMCIntegration\Traits;

use Manu\GMCIntegration\Services\GMCService;
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
        // Remove automatic sync on create
        // static::created(function ($model) { ... });

        // Remove automatic sync on update
        // static::updated(function ($model) { ... });

        // Only sync on status change to inactive
        static::updated(function ($model) {
            if (!$model->shouldSyncToGMC()) {
                return;
            }
            
            // Check if status changed to inactive
            if ($model->wasChanged('status') && $model->status === 'inactive') {
                if (Config::get('gmc.auto_sync_enabled', true)) {
                    dispatch(function () use ($model) {
                        $model->syncToGMC();
                    })->afterResponse();
                }
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
            Log::error("Failed to sync product {$this->getKey()} to GMC", [
                'table' => $this->getTable(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (Config::get('gmc.throw_sync_exceptions', false)) {
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
            Log::error("Failed to delete product {$this->getKey()} from GMC", [
                'table' => $this->getTable(),
                'error' => $e->getMessage()
            ]);
            
            if (Config::get('gmc.throw_sync_exceptions', false)) {
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
        return !empty($this->getGMCId()) && !empty($this->getGMCLastSync());
    }

    /**
     * Get the sync status
     */
    public function getGMCSyncStatus(): array
    {
        return [
            'is_synced' => $this->isSyncedWithGMC(),
            'gmc_id' => $this->getGMCId(),
            'last_sync' => $this->getGMCLastSync(),
            'sync_enabled' => $this->shouldSyncToGMC(),
        ];
    }

    /**
     * Check if this specific product should sync to GMC
     * Override this method to make syncing optional per product
     */
    public function shouldSyncToGMC(): bool
    {
        // Check if model has sync_enabled field
        if (isset($this->sync_enabled)) {
            return (bool) $this->sync_enabled;
        }
        
        // Check if model has gmc_sync_enabled field
        if (isset($this->gmc_sync_enabled)) {
            return (bool) $this->gmc_sync_enabled;
        }
        
        // Default to true if no sync control field exists
        return true;
    }

    /**
     * Update GMC data after successful sync
     */
    protected function updateGMCData($result): void
    {
        $updateData = [
            'gmc_last_sync' => Carbon::now()
        ];
        
        if (isset($result->id)) {
            $updateData['gmc_product_id'] = $result->id;
        }
        
        $this->update($updateData);
    }

    /**
     * Clear GMC data after deletion
     */
    protected function clearGMCData(): void
    {
        $this->update([
            'gmc_product_id' => null,
            'gmc_last_sync' => null
        ]);
    }

    // Required method that models must implement
    abstract public function prepareGMCData(): array;

    /**
     * Get GMC product ID
     */
    public function getGMCId(): ?string
    {
        return $this->gmc_product_id ?? (string) $this->getKey();
    }

    /**
     * Get last sync timestamp
     */
    public function getGMCLastSync(): ?string
    {
        return $this->gmc_last_sync?->toISOString();
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
