<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        
        if (!Schema::hasTable('simulation_events')) {
            Schema::create('simulation_events', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
    
                
                $table->string('type')->default('one-off'); 
    
               
                $table->dateTime('start_moment')->nullable();
                $table->dateTime('end_moment')->nullable();
    
                
                $table->string('recurring_schedule')->nullable(); 
                $table->date('recurring_start_date')->nullable();
                $table->date('recurring_end_date')->nullable();
                $table->time('recurring_start_time')->nullable();
                $table->time('recurring_end_time')->nullable();
    
                $table->timestamps();
            });
        }
    }

  
     
    public function down(): void
    {
        Schema::dropIfExists('simulation_events');
    }
};