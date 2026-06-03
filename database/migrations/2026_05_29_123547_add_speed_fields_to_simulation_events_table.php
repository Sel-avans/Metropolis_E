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
        Schema::table('simulation_events', function (Blueprint $table) {
            $table->double('remaining_base_duration')->default(0)->after('type');
            $table->timestamp('last_updated_at')->useCurrent()->after('remaining_base_duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simulation_events', function (Blueprint $table) {
            $table->dropColumn(['remaining_base_duration', 'last_updated_at']);
        });
    }
};