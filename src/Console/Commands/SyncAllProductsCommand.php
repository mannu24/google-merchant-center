<?php

namespace Mannu24\GMCIntegration\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Mannu24\GMCIntegration\Services\GMCService;
use Mannu24\GMCIntegration\Traits\SyncsWithGMC;

class SyncAllProductsCommand extends Command
{
    protected $signature = 'gmc:sync-all 
                            {model? : Model class to sync}
                            {--chunk=50 : Number of products to process in each batch}
                            {--force : Force sync even if recently synced}
                            {--dry-run : Show what would be synced without actually syncing}
                            {--filter= : Filter products (e.g., "status=active")}';

    protected $description = 'Sync all products with Google Merchant Center';

    public function handle()
    {
        $modelClass = $this->argument('model') ?? config('gmc.default_model');
        
        if (!$modelClass) {
            $this->error('Please specify a model class or set default_model in config');
            return 1;
        }

        if (!class_exists($modelClass)) {
            $this->error("Model class {$modelClass} does not exist");
            return 1;
        }

        $model = new $modelClass;
        
        if (!in_array(SyncsWithGMC::class, class_uses_recursive($model))) {
            $this->error("Model {$modelClass} must use SyncsWithGMC trait");
            return 1;
        }

        $gmcService = app(GMCService::class);
        $chunkSize = (int) $this->option('chunk');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $filter = $this->option('filter');

        $this->info("Starting bulk sync for {$modelClass}...");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No actual syncing will occur");
        }
        
        $query = $this->buildQuery($modelClass, $force, $filter);
        $total = $query->count();
        
        if ($total === 0) {
            $this->info("No products found to sync");
            return 0;
        }

        $this->info("Found {$total} products to sync");
        
        if ($dryRun) {
            $this->showDryRunResults($query, $total);
            return 0;
        }

        return $this->performSync($query, $gmcService, $chunkSize, $total);
    }

    protected function buildQuery(string $modelClass, bool $force, ?string $filter)
    {
        $query = $modelClass::query();
        
        if ($filter) {
            $this->applyFilter($query, $filter);
        }
        
        $query->where(function($q) {
            $q->where('sync_enabled', true)
              ->orWhere('gmc_sync_enabled', true)
              ->orWhereNull('sync_enabled');
        });
        
        if (!$force) {
            $query->where(function($q) {
                $q->whereNull('gmc_last_sync')
                  ->orWhere('gmc_last_sync', '<', now()->subHours(24));
            });
        }

        return $query;
    }

    protected function applyFilter($query, string $filter)
    {
        $filters = explode(',', $filter);
        
        foreach ($filters as $filterItem) {
            $parts = explode('=', trim($filterItem));
            if (count($parts) === 2) {
                $field = trim($parts[0]);
                $value = trim($parts[1]);
                $query->where($field, $value);
            }
        }
    }

    protected function showDryRunResults($query, int $total)
    {
        $this->info("Would sync {$total} products:");
        
        $sample = $query->limit(10)->get();
        foreach ($sample as $product) {
            $name = $product->name ?? 'Unknown';
            $this->line("  - {$product->getKey()}: {$name}");
        }
        
        if ($total > 10) {
            $this->line("  ... and " . ($total - 10) . " more");
        }
    }

    protected function performSync($query, GMCService $gmcService, int $chunkSize, int $total)
    {
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('verbose');
        
        $successCount = 0;
        $errorCount = 0;
        $startTime = microtime(true);

        try {
            $query->chunk($chunkSize, function ($products) use (&$successCount, &$errorCount, $bar, $gmcService) {
                $result = $gmcService->syncMultipleProducts($products);
                $successCount += $result['successes'];
                $errorCount += count($result['errors']);
                
                $bar->advance($products->count());
            });

            $bar->finish();
            $this->newLine();
            
            $duration = round(microtime(true) - $startTime, 2);
            $this->info("Sync completed in {$duration}s!");
            $this->info("Successful: {$successCount}");
            
            if ($errorCount > 0) {
                $this->warn("Errors: {$errorCount}");
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine();
            
            $this->error("Sync failed: " . $e->getMessage());
            return 1;
        }
    }
} 