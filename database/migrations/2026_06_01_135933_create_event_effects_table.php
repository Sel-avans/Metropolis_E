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
        Schema::create('event_effects', function (Blueprint $table) {
            $table->id();
            
            // Link to the simulation event
            $table->foreignId('simulation_event_id')->constrained()->onDelete('cascade');
            
            // Link to the city function
            $table->foreignId('city_function_id')->constrained()->onDelete('cascade');
            
            // The value of the effect
            $table->integer('modifier'); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_effects');
    }
};