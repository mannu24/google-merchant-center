<?php

namespace Mannu24\GMCIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GMCProduct extends Model
{
    protected $table = 'gmc_products';
    
    protected $fillable = [
        'product_id', 'product_type', 'sync_enabled', 'gmc_product_id',
        'gmc_last_sync', 'gmc_sync_data', 'sync_status', 'last_error', 'last_error_at',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'gmc_last_sync' => 'datetime',
        'gmc_sync_data' => 'array',
        'last_error_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo($this->product_type, 'product_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(GMCSyncLog::class);
    }

    public function isSynced(): bool
    {
        return $this->sync_status === 'synced' && !empty($this->gmc_product_id);
    }

    public function isSyncEnabled(): bool
    {
        return $this->sync_enabled && $this->sync_status !== 'disabled';
    }

    public function getLastSuccessfulSync()
    {
        return $this->syncLogs()
            ->where('status', 'success')
            ->latest()
            ->first();
    }

    public function getLastError()
    {
        return $this->syncLogs()
            ->where('status', 'failed')
            ->latest()
            ->first();
    }

    public function updateSyncStatus(string $status, ?string $error = null): void
    {
        $this->update([
            'sync_status' => $status,
            'last_error' => $error,
            'last_error_at' => $error ? now() : null,
        ]);
    }

    public function markAsSynced(string $gmcProductId, array $syncData = []): void
    {
        $this->update([
            'sync_status' => 'synced',
            'gmc_product_id' => $gmcProductId,
            'gmc_last_sync' => now(),
            'gmc_sync_data' => $syncData,
            'last_error' => null,
            'last_error_at' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'sync_status' => 'failed',
            'last_error' => $error,
            'last_error_at' => now(),
        ]);
    }
} 