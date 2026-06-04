<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('effects', function (Blueprint $table) {
            $table->unsignedBigInteger('function_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('effects', function (Blueprint $table) {
        
            DB::table('effects')->whereNull('function_id')->update(['function_id' => 0]);

            
            $table->unsignedBigInteger('function_id')->nullable(false)->change();
        });
    }
};
