<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        
        if (Schema::hasColumn('effects', 'simulation_event_id')) {
            return;
        }

        Schema::table('effects', function (Blueprint $table) {
            
            if (Schema::hasTable('simulation_events')) {
                $table->foreignId('simulation_event_id')
                    ->nullable()
                    ->after('function_id')
                    ->constrained('simulation_events')
                    ->nullOnDelete();
            } else {
                
                $table->unsignedBigInteger('simulation_event_id')->nullable()->after('function_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('effects', function (Blueprint $table) {
            
            if (Schema::hasColumn('effects', 'simulation_event_id')) {
                $table->dropForeign(['simulation_event_id']);
                $table->dropColumn('simulation_event_id');
            }
        });
    }
};