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
        Schema::table('products', function (Blueprint $table) {
            // GMC sync control fields
            $table->boolean('sync_enabled')->default(true)->after('status');
            $table->string('gmc_sync_enabled_field')->nullable()->after('sync_enabled');
            
            // GMC tracking fields
            $table->string('gmc_product_id')->nullable()->after('gmc_sync_enabled_field');
            $table->timestamp('gmc_last_sync')->nullable()->after('gmc_product_id');
            
            // Indexes for better performance
            $table->index(['sync_enabled', 'status']);
            $table->index('gmc_product_id');
            $table->index('gmc_last_sync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['sync_enabled', 'status']);
            $table->dropIndex(['gmc_product_id']);
            $table->dropIndex(['gmc_last_sync']);
            
            // Drop columns
            $table->dropColumn([
                'sync_enabled',
                'gmc_sync_enabled_field',
                'gmc_product_id',
                'gmc_last_sync'
            ]);
        });
    }
}; 