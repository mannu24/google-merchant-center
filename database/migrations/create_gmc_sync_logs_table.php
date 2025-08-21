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
        Schema::create('gmc_sync_logs', function (Blueprint $table) {
            $table->id();
            
            // Reference to the GMC product
            $table->unsignedBigInteger('gmc_product_id');
            
            // Sync details
            $table->enum('action', ['create', 'update', 'delete', 'sync']);
            $table->enum('status', ['success', 'failed', 'pending']);
            $table->text('error_message')->nullable();
            
            // Request/Response data
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            
            // Performance metrics
            $table->integer('response_time_ms')->nullable();
            $table->string('gmc_product_id_gmc')->nullable(); // GMC's actual product ID
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('gmc_product_id');
            $table->index(['action', 'status']);
            $table->index('created_at');
            
            // Foreign key
            $table->foreign('gmc_product_id')->references('id')->on('gmc_products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmc_sync_logs');
    }
}; 