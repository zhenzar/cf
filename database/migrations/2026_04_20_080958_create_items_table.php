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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_file_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('keyword')->nullable();
            $table->unsignedInteger('worth_copper')->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->string('item_type')->nullable();   // weapon/armor/clothing/instrument/treasure
            $table->string('slot')->nullable();         // body/head/...
            $table->string('material')->nullable();
            $table->unsignedInteger('weight_pounds')->nullable();
            $table->unsignedInteger('weight_ounces')->nullable();
            $table->string('weapon_class')->nullable();
            $table->string('damage_type')->nullable();
            $table->string('av_damage')->nullable();
            $table->text('raw_text');
            $table->string('hash', 64)->index(); // for dedup
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
