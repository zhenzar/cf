<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('area_id')->nullable()->constrained()->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('mob_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mob_id')->constrained()->onDelete('cascade');
            $table->string('slot'); // mainhand, offhand, head, body, etc.
            $table->string('item_name'); // (Illusionary) (Glowing) a hand-axe covered in ice
            $table->foreignId('item_id')->nullable()->constrained()->onDelete('set null'); // link to items table if known
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mob_equipment');
        Schema::dropIfExists('mobs');
    }
};
