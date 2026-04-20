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
        Schema::create('log_files', function (Blueprint $table) {
            $table->id();
            $table->string('path', 1024)->unique();
            $table->string('filename');
            $table->string('source', 20)->default('scan'); // scan | upload
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('reviewed')->default(false);
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_files');
    }
};
