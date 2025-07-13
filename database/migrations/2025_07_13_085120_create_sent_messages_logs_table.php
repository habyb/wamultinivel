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
        Schema::create('sent_messages_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_message_id')->constrained()->onDelete('cascade');
            $table->string('contact_name');
            $table->string('remote_jid');
            $table->string('message_status')->nullable();
            $table->timestamp('sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sent_messages_logs');
    }
};
