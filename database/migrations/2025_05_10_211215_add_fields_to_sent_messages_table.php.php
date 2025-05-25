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
            $table->json('cities')->nullable()->after('title');
            $table->json('neighborhoods')->nullable();
            $table->json('genders')->nullable();
            $table->json('age_groups')->nullable();
            $table->json('concerns_01')->nullable();
            $table->json('concerns_02')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sent_messages', function (Blueprint $table) {
            $table->dropColumn(
                [
                    'cities',
                    'neighborhoods',
                    'genders',
                    'age_groups',
                    'concerns_01',
                    'concerns_02'
                ]
            );
        });
    }
};
