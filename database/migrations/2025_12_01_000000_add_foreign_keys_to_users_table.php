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
        // Like in your original project, add the foreign key constraints
        // after the base tables (user_roles, households) already exist.
        if (config('database.default') !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('user_role_id')
                    ->references('id')
                    ->on('user_roles')
                    ->onDelete('cascade');

                $table->foreign('household_id')
                    ->references('id')
                    ->on('households')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['user_role_id']);
                $table->dropForeign(['household_id']);
            });
        }
    }
};


