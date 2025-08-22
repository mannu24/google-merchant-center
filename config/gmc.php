<?php

return [
    'merchant_id' => env('GMC_MERCHANT_ID', ''),
    'service_account_json' => env('GMC_SERVICE_JSON', __DIR__ . '/../storage/app/mca.json'),
    'throw_sync_exceptions' => env('GMC_THROW_EXCEPTIONS', true),
    'auto_sync_enabled' => env('GMC_AUTO_SYNC', true),
    'use_queue' => env('GMC_USE_QUEUE', false),
    'queue_name' => env('GMC_QUEUE_NAME', 'default'),
    'default_model' => env('GMC_DEFAULT_MODEL', 'App\\Models\\Product'),
    'batch_size' => env('GMC_BATCH_SIZE', 50),
    'retry_attempts' => env('GMC_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('GMC_RETRY_DELAY', 1000),
    'cache_duplicate_syncs' => env('GMC_CACHE_DUPLICATE_SYNCS', true),
    'cache_duration' => env('GMC_CACHE_DURATION', 300),
    'sync_enabled_field' => env('GMC_SYNC_ENABLED_FIELD', 'sync_enabled'),
    'gmc_sync_enabled_field' => env('GMC_SYNC_ENABLED_FIELD_ALT', 'gmc_sync_enabled'),
    'log_sync_events' => env('GMC_LOG_SYNC_EVENTS', true),
    'log_level' => env('GMC_LOG_LEVEL', 'info'),
    'rate_limit_delay' => env('GMC_RATE_LIMIT_DELAY', 100000),
    'validate_before_sync' => env('GMC_VALIDATE_BEFORE_SYNC', true),
    'strict_validation' => env('GMC_STRICT_VALIDATION', false),
];
