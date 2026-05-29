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
        Schema::create('simulation_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
    
            // Type can be 'one-off' or 'recurring'
            $table->string('type')->default('one-off'); 
    
            // Fields for one-off events
            $table->dateTime('start_moment')->nullable();
            $table->dateTime('end_moment')->nullable();
    
            // Field for recurring events (e.g., 'daily', 'weekly', 'weekends')
            $table->string('recurring_schedule')->nullable(); 
    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulation_events');
    }
};
