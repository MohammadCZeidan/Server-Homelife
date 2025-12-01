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
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('week_id')->constrained('weeks')->onDelete('cascade');
            $table->integer('day'); // 0-6 (Sunday-Saturday) or 1-7
            $table->enum('slot', ['breakfast', 'lunch', 'dinner', 'snack'])->default('dinner');
            $table->foreignId('recipe_id')->constrained('recipes')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['week_id', 'day', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};

