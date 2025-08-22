<?php

namespace Mannu24\GMCIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GMCSyncLog extends Model
{
    protected $table = 'gmc_sync_logs';
    
    protected $fillable = [
        'gmc_product_id', 'action', 'status', 'error_message', 'request_data',
        'response_data', 'response_time_ms', 'gmc_product_id_gmc',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    public function gmcProduct(): BelongsTo
    {
        return $this->belongsTo(GMCProduct::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getFormattedResponseTime(): string
    {
        if (!$this->response_time_ms) {
            return 'N/A';
        }

        if ($this->response_time_ms < 1000) {
            return $this->response_time_ms . 'ms';
        }

        return round($this->response_time_ms / 1000, 2) . 's';
    }

    public function getErrorSummary(): string
    {
        if (!$this->error_message) {
            return 'No error';
        }

        return substr($this->error_message, 0, 100) . (strlen($this->error_message) > 100 ? '...' : '');
    }
} 