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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('instructions');
            $table->json('tags')->nullable(); // ['vegetarian', 'quick', 'dinner', etc.]
            $table->integer('servings')->nullable();
            $table->integer('prep_time')->nullable(); // minutes
            $table->integer('cook_time')->nullable(); // minutes
            $table->foreignId('household_id')->nullable()->constrained('households')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};

