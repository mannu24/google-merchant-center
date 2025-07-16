<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gmc_products', function (Blueprint $table) {
            $table->id();
            
            // Reference to the main product
            $table->unsignedBigInteger('product_id');
            $table->string('product_type'); // e.g., 'App\Models\Product'
            
            // GMC sync control
            $table->boolean('sync_enabled')->default(true);
            
            // GMC tracking data
            $table->string('gmc_product_id')->nullable();
            $table->timestamp('gmc_last_sync')->nullable();
            $table->json('gmc_sync_data')->nullable(); // Store last sync data
            
            // Sync status and errors
            $table->enum('sync_status', ['pending', 'synced', 'failed', 'disabled'])->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['product_id', 'product_type']);
            $table->index(['sync_enabled', 'sync_status']);
            $table->index('gmc_product_id');
            $table->index('gmc_last_sync');
            
            // Unique constraint to prevent duplicate entries
            $table->unique(['product_id', 'product_type'], 'gmc_products_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmc_products');
    }
}; 