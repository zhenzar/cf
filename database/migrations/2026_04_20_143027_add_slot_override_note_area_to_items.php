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
        Schema::table('items', function (Blueprint $table) {
            $table->string('slot_override')->nullable()->after('slot');
            $table->text('note')->nullable()->after('stats_hash');
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete()->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn(['slot_override', 'note', 'area_id']);
        });
    }
};
