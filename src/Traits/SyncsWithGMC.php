<?php

namespace Mannu24\GMCIntegration\Traits;

use Mannu24\GMCIntegration\Services\GMCService;
use Mannu24\GMCIntegration\Models\GMCProduct;
use Mannu24\GMCIntegration\Models\GMCSyncLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

trait SyncsWithGMC
{
    public static function bootSyncsWithGMC()
    {
        static::created(function ($model) {
            if (!$model->shouldSyncToGMC() || !Config::get('gmc.auto_sync_enabled', true)) {
                return;
            }
            
            dispatch(function () use ($model) {
                $model->syncToGMC();
            })->afterResponse();
        });

        static::updated(function ($model) {
            if (!$model->shouldSyncToGMC() || !Config::get('gmc.auto_sync_enabled', true)) {
                return;
            }
            
            dispatch(function () use ($model) {
                $model->syncToGMC();
            })->afterResponse();
        });

        static::deleted(function ($model) {
            if (!$model->shouldSyncToGMC() || !Config::get('gmc.auto_sync_enabled', true)) {
                return;
            }
            
            if (method_exists($model, 'shouldDeleteFromGMC') && !$model->shouldDeleteFromGMC()) {
                return;
            }
            
            dispatch(function () use ($model) {
                $model->deleteFromGMC();
            })->afterResponse();
        });
    }

    public function getGMCProduct(): ?GMCProduct
    {
        return GMCProduct::where('product_id', $this->getKey())
            ->where('product_type', get_class($this))
            ->first();
    }

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

    public function syncToGMC()
    {
        try {
            $gmcProduct = $this->createGMCProduct();
            
            if (!$gmcProduct->isSyncEnabled()) {
                return false;
            }
            
            $gmcProduct->updateSyncStatus('pending');
            
            $gmcService = app(GMCService::class);
            $result = $gmcService->syncProduct($this);
            
            $this->updateGMCData($result);
            
            return $result;
        } catch (\Exception $e) {
            $gmcProduct = $this->getGMCProduct();
            if ($gmcProduct) {
                $gmcProduct->markAsFailed($e->getMessage());
            }
            
            if (Config::get('gmc.throw_sync_exceptions', true)) {
                throw $e;
            }
            
            return false;
        }
    }

    public function syncwithgmc()
    {
        return $this->syncToGMC();
    }

    public function forceSyncToGMC()
    {
        return $this->syncToGMC();
    }

    public function forceUpdateInGMC()
    {
        try {
            $gmcService = app(GMCService::class);
            return $gmcService->forceUpdateProduct($this);
        } catch (\Exception $e) {
            if (Config::get('gmc.throw_sync_exceptions', true)) {
                throw $e;
            }
            return false;
        }
    }

    public function deleteFromGMC()
    {
        try {
            $gmcService = app(GMCService::class);
            $productId = $this->getGMCId();
            
            if (!$productId) {
                return false;
            }
            
            $gmcService->deleteProduct($productId);
            $this->clearGMCData();
            
            return true;
        } catch (\Exception $e) {
            if (Config::get('gmc.throw_sync_exceptions', true)) {
                throw $e;
            }
            return false;
        }
    }

    public function isSyncedWithGMC(): bool
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->isSynced() : false;
    }

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

    public function shouldSyncToGMC(): bool
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->isSyncEnabled() : true;
    }

    public function enableGMCSync(): void
    {
        $gmcProduct = $this->createGMCProduct();
        $gmcProduct->update(['sync_enabled' => true]);
    }

    public function disableGMCSync(): void
    {
        $gmcProduct = $this->getGMCProduct();
        if ($gmcProduct) {
            $gmcProduct->update(['sync_enabled' => false]);
        }
    }

    public function getGMCSyncLogs()
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->syncLogs() : collect();
    }

    public function getLastSuccessfulGMCSync()
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->getLastSuccessfulSync() : null;
    }

    public function getLastGMCError()
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->getLastError() : null;
    }

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

    abstract public function prepareGMCData(): array;

    public function getGMCId(): ?string
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->gmc_product_id : null;
    }

    public function getGMCLastSync(): ?string
    {
        $gmcProduct = $this->getGMCProduct();
        return $gmcProduct ? $gmcProduct->gmc_last_sync?->toISOString() : null;
    }

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
