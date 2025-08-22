<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Merchant Center Configuration
    |--------------------------------------------------------------------------
    */

    'merchant_id' => env('GMC_MERCHANT_ID', ''),
    
    'service_account_json' => env('GMC_SERVICE_JSON', __DIR__ . '/../storage/app/mca.json'),
    
    // Whether to throw exceptions on sync failures (useful for debugging)
    'throw_sync_exceptions' => env('GMC_THROW_EXCEPTIONS', false),
    
    // Enable/disable automatic syncing
    'auto_sync_enabled' => env('GMC_AUTO_SYNC', true),
    
    // Queue configuration for async processing (optional)
    'use_queue' => env('GMC_USE_QUEUE', false),
    'queue_name' => env('GMC_QUEUE_NAME', 'default'),
    
    // Default model for bulk operations
    'default_model' => env('GMC_DEFAULT_MODEL', 'App\\Models\\Product'),
    
    // Batch processing settings
    'batch_size' => env('GMC_BATCH_SIZE', 50),
    'retry_attempts' => env('GMC_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('GMC_RETRY_DELAY', 1000), // milliseconds
    
    // Cache settings
    'cache_duplicate_syncs' => env('GMC_CACHE_DUPLICATE_SYNCS', true),
    'cache_duration' => env('GMC_CACHE_DURATION', 300), // seconds
    
    // Sync control fields (optional per-product sync control)
    'sync_enabled_field' => env('GMC_SYNC_ENABLED_FIELD', 'sync_enabled'),
    'gmc_sync_enabled_field' => env('GMC_SYNC_ENABLED_FIELD_ALT', 'gmc_sync_enabled'),
    
    // Logging settings
    'log_sync_events' => env('GMC_LOG_SYNC_EVENTS', true),
    'log_level' => env('GMC_LOG_LEVEL', 'info'),
    
    // Rate limiting
    'rate_limit_delay' => env('GMC_RATE_LIMIT_DELAY', 100000), // microseconds
    
    // Validation settings
    'validate_before_sync' => env('GMC_VALIDATE_BEFORE_SYNC', true),
    'strict_validation' => env('GMC_STRICT_VALIDATION', false),
];
