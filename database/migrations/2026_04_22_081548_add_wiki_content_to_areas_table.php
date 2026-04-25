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
        Schema::table('areas', function (Blueprint $table) {
            $table->longText('wiki_content')->nullable()->after('url');
            $table->timestamp('wiki_fetched_at')->nullable()->after('wiki_content');
            $table->string('wiki_title')->nullable()->after('wiki_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn(['wiki_content', 'wiki_fetched_at', 'wiki_title']);
        });
    }
};
