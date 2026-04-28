<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks for MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['log_file_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        // Make column nullable and add new foreign key
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('log_file_id')->nullable()->change();
            $table->foreign('log_file_id')->references('id')->on('log_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['log_file_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('log_file_id')->nullable(false)->change();
            $table->foreign('log_file_id')->references('id')->on('log_files')->cascadeOnDelete();
        });
    }
};
