<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('grid_cells', function (Blueprint $table) {
        $table->id();
        $table->integer('row');
        $table->integer('col');
        $table->unsignedBigInteger('function_id')->nullable();
        $table->timestamps();

        $table->foreign('function_id')
              ->references('id')
              ->on('city_functions')
              ->onDelete('set null');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grid_cells');
    }
};
