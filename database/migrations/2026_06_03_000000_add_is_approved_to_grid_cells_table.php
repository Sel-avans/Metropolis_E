<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        
        if (!Schema::hasColumn('grid_cells', 'is_approved')) {
            Schema::table('grid_cells', function (Blueprint $table) {
                $table->boolean('is_approved')->default(false)->after('function_id');
            });
        }
    }

    public function down(): void
    {
        
        if (Schema::hasColumn('grid_cells', 'is_approved')) {
            Schema::table('grid_cells', function (Blueprint $table) {
                $table->dropColumn('is_approved');
            });
        }
    }
};