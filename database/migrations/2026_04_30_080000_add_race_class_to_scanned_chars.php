<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scanned_chars', function (Blueprint $table) {
            $table->string('race')->nullable()->after('name');
            $table->string('class')->nullable()->after('race');
            $table->integer('level')->nullable()->after('class');
        });
    }

    public function down(): void
    {
        Schema::table('scanned_chars', function (Blueprint $table) {
            $table->dropColumn(['race', 'class', 'level']);
        });
    }
};
