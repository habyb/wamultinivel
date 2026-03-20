<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_id')->nullable()->after('remoteJid');
        });

        DB::statement('
            UPDATE users
            SET whatsapp_id = "remoteJid"
            WHERE whatsapp_id IS NULL AND "remoteJid" IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Desfaz a criação da coluna em caso de rollback
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('whatsapp_id');
        });
    }
};
