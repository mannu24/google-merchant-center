<?php

namespace Manu\GMCIntegration\Traits;

use Manu\GMCIntegration\Repositories\Interfaces\GMCRepositoryInterface;
use Illuminate\Support\Facades\Log;

trait SyncsWithGMC
{
    public static function bootSyncsWithGMC()
    {
        static::created(function ($model) {
            if (method_exists($model, 'shouldSyncOnCreate') && !$model->shouldSyncOnCreate()) {
                return;
            }
            $model->syncToGMC();
        });

        static::updated(function ($model) {
            if (method_exists($model, 'shouldSyncOnUpdate') && !$model->shouldSyncOnUpdate()) {
                return;
            }
            $model->syncToGMC();
        });

        static::deleted(function ($model) {
            if (method_exists($model, 'shouldDeleteFromGMC') && !$model->shouldDeleteFromGMC()) {
                return;
            }
            $model->deleteFromGMC();
        });
    }

    public function syncToGMC()
    {
        try {
            $gmcRepo = app(GMCRepositoryInterface::class);
            $gmcRepo->uploadProduct($this->getGMCData());
            Log::info("Product {$this->id} synced to GMC.");
        } catch (\Exception $e) {
            Log::error("Failed to sync product {$this->id} to GMC: " . $e->getMessage());
        }
    }

    public function deleteFromGMC()
    {
        try {
            $gmcRepo = app(GMCRepositoryInterface::class);
            $gmcRepo->deleteProduct($this->getGMCId());
            Log::info("Product {$this->id} deleted from GMC.");
        } catch (\Exception $e) {
            Log::error("Failed to delete product {$this->id} from GMC: " . $e->getMessage());
        }
    }

    abstract public function getGMCData(): array;

    public function getGMCId()
    {
        return $this->gmc_product_id ?? null;
    }
}
