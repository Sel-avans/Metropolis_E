<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulation_events', function (Blueprint $table) {
            if (! Schema::hasColumn('simulation_events', 'recurring_start_date')) {
                $table->date('recurring_start_date')->nullable()->after('recurring_schedule');
            }

            if (! Schema::hasColumn('simulation_events', 'recurring_end_date')) {
                $table->date('recurring_end_date')->nullable()->after('recurring_start_date');
            }

            if (! Schema::hasColumn('simulation_events', 'recurring_start_time')) {
                $table->time('recurring_start_time')->nullable()->after('recurring_end_date');
            }

            if (! Schema::hasColumn('simulation_events', 'recurring_end_time')) {
                $table->time('recurring_end_time')->nullable()->after('recurring_start_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('simulation_events', function (Blueprint $table) {
            $columns = [
                'recurring_start_date',
                'recurring_end_date',
                'recurring_start_time',
                'recurring_end_time',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('simulation_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
