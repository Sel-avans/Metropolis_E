<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_routes', function (Blueprint $table) {
            $table->unsignedTinyInteger('end_row')->nullable()->after('start_col');
            $table->unsignedTinyInteger('end_col')->nullable()->after('end_row');
            $table->foreignId('end_function_id')
                ->nullable()
                ->after('end_col')
                ->constrained('city_functions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_routes', function (Blueprint $table) {
            $table->dropForeign(['end_function_id']);
            $table->dropColumn(['end_row', 'end_col', 'end_function_id']);
        });
    }
};
