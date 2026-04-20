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
        Schema::table('log_files', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->unique()->after('size');
            $table->longText('content')->nullable()->after('content_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_files', function (Blueprint $table) {
            $table->dropUnique(['content_hash']);
            $table->dropColumn(['content_hash', 'content']);
        });
    }
};
