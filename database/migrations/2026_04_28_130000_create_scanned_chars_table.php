<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanned_chars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('log_file_id')->nullable()->constrained()->onDelete('set null');
            $table->text('source_line')->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanned_chars');
    }
};
