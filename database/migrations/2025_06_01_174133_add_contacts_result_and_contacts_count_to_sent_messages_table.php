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
            $table->json('contacts_result')->nullable()->after('description');
            $table->unsignedInteger('contacts_count')->default(0)->after('contacts_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sent_messages', function (Blueprint $table) {
            $table->dropColumn(['contacts_result', 'contacts_count']);
        });
    }
};
