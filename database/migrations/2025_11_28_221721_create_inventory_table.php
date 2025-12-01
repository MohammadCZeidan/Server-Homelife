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
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->onDelete('cascade');
            $table->decimal('quantity', 10, 2);
            $table->foreignId('unit_id')->constrained('units')->onDelete('restrict');
            $table->date('expiry_date')->nullable();
            $table->string('location')->nullable(); // e.g., 'Fridge', 'Pantry', 'Freezer'
            $table->foreignId('household_id')->constrained('households')->onDelete('cascade');
            $table->timestamps();
            
            $table->index('expiry_date');
            $table->index('household_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};

