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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->onDelete('cascade');
            $table->string('store')->nullable();
            $table->string('receipt_link')->nullable(); // URL or file path
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('category')->nullable(); // 'groceries', 'dining', 'snacks', etc.
            $table->text('note')->nullable();
            $table->timestamps();
            
            $table->index(['household_id', 'date']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

