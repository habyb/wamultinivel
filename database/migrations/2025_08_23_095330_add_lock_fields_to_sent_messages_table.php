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
            Schema::table('sent_messages', function (Blueprint $table) {
                // Token único que identifica qual worker "claimou" a mensagem
                $table->uuid('lock_token')->nullable()->index()->after('status');

                // Momento em que a mensagem foi bloqueada para processamento
                $table->timestamp('locked_at')->nullable()->after('lock_token');

                // Momento em que a mensagem foi efetivamente enviada com sucesso
                $table->timestamp('sent_ok_at')->nullable()->after('locked_at');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sent_messages', function (Blueprint $table) {
            $table->dropColumn(['lock_token', 'locked_at', 'sent_ok_at']);
        });
    }
};
