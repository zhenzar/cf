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
        Schema::create('item_log_file', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('log_file_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['item_id', 'log_file_id']);
        });

        // Backfill pivot from existing items.log_file_id.
        \DB::statement('INSERT OR IGNORE INTO item_log_file (item_id, log_file_id, created_at)
                        SELECT id, log_file_id, created_at FROM items WHERE log_file_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_log_file');
    }
};
