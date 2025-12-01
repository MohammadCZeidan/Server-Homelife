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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('household_id')->constrained('households')->onDelete('cascade');
            $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->decimal('calories', 8, 2)->nullable()->default(0); // per 100g or per unit
            $table->decimal('protein', 8, 2)->nullable()->default(0); // grams
            $table->decimal('carbs', 8, 2)->nullable()->default(0); // grams
            $table->decimal('fat', 8, 2)->nullable()->default(0); // grams
            $table->timestamps();
            
            $table->index('household_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};

