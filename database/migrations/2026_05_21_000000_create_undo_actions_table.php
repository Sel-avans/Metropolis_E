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
        // Als de tabel ergens half bestaat, gooien we hem eerst veilig weg
        Schema::dropIfExists('undo_actions');

        // We maken de tabel nu SCHOON aan met alle benodigde kolommen
        Schema::create('undo_actions', function (Blueprint $table) {
            $table->id();
            $table->integer('row');
            $table->integer('col');
            $table->integer('new_row')->nullable(); 
            $table->integer('new_col')->nullable(); 
            $table->unsignedBigInteger('previous_function_id')->nullable();
            $table->string('action_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('undo_actions');
    }
};