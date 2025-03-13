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
            // remoteJid
            $table->string('remote_jid')->nullable()->after('updated_at');
            $table->boolean('is_remote_jid')->default(false);
            // Code
            $table->string('code', 10)->nullable();
            // Invitation Code
            $table->string('invitation_code', 10)->nullable();
            // Name
            $table->boolean('is_question_name')->default(false);
            $table->boolean('is_add_name')->default(false);
            // City
            $table->boolean('is_question_city')->default(false);
            $table->string('city')->nullable();
            $table->boolean('is_add_city')->default(false);
            // Neighborhood
            $table->boolean('is_question_neighborhood')->default(false);
            $table->string('neighborhood')->nullable();
            $table->boolean('is_add_neighborhood')->default(false);
            // Concern 01
            $table->boolean('is_question_concern_01')->default(false);
            $table->string('concern_01')->nullable();
            $table->boolean('is_add_concern_01')->default(false);
            // Concern 02
            $table->boolean('is_question_concern_02')->default(false);
            $table->string('concern_02')->nullable();
            $table->boolean('is_add_concern_02')->default(false);
            // Gender
            $table->boolean('is_question_gender')->default(false);
            $table->string('gender')->nullable();
            $table->boolean('is_add_gender')->default(false);
            // Date of Birth
            $table->boolean('is_question_date_of_birth')->default(false);
            $table->string('date_of_birth')->nullable();
            $table->boolean('is_add_date_of_birth')->default(false);
            // Email
            $table->boolean('is_question_email')->default(false);
            $table->boolean('is_add_email')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(
                [
                    'remote_jid',
                    'is_remote_jid',
                    'code',
                    'invitation_code',
                    'is_question_name',
                    'is_add_name',
                    'is_question_city',
                    'city',
                    'is_add_city',
                    'is_question_neighborhood',
                    'neighborhood',
                    'is_add_neighborhood',
                    'is_question_concern_01',
                    'concern_01',
                    'is_add_concern_01',
                    'is_question_concern_02',
                    'concern_02',
                    'is_add_concern_02',
                    'is_question_gender',
                    'gender',
                    'is_add_gender',
                    'is_question_date_of_birth',
                    'date_of_birth',
                    'is_add_date_of_birth',
                    'is_question_email',
                    'is_add_email'
                ]
            );
        });
    }
};
