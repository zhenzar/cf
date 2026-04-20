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
            $table->string('damage_dice', 20)->nullable()->after('weapon_class');
            $table->string('weapon_qualifier', 40)->nullable()->after('weapon_class'); // e.g. "two-handed"
            $table->string('attack_type', 40)->nullable()->after('damage_type');        // e.g. "crush" (weapon verb)
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['damage_dice', 'weapon_qualifier', 'attack_type']);
        });
    }
};
