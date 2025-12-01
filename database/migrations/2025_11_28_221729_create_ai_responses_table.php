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
        Schema::create('ai_responses', function (Blueprint $table) {
            $table->id();
            $table->text('json_input');
            $table->text('json_output');
            $table->string('type')->nullable(); // 'recipe_suggestion', 'substitution', 'weekly_insights', etc.
            $table->foreignId('household_id')->constrained('households')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['household_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_responses');
    }
};

