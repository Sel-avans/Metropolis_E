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
    Schema::create('effects', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('function_id');
        $table->string('category');
        $table->integer('value');
        $table->timestamps();

        $table->foreign('function_id')
              ->references('id')
              ->on('city_functions')
              ->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('effects');
    }
};
