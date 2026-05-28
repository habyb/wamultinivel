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
        Schema::table('users', function (Blueprint $table) {
            $table->index('code');
            $table->index('invitation_code');
            $table->index('is_add_date_of_birth');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->dropIndex(['invitation_code']);
            $table->dropIndex(['is_add_date_of_birth']);
            $table->dropIndex(['created_at']);
        });
    }
};
