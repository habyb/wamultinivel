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
        Schema::table('sent_messages', function (Blueprint $table) {
            $table->string('template_language')->nullable()->after('id');
            $table->json('template_components')->nullable()->after('template_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sent_messages', function (Blueprint $table) {
            $table->dropColumn(['template_language', 'template_components']);
        });
    }
};
