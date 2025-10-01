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
        Schema::create('mp_position_menus', function (Blueprint $table) {
            $table->unsignedInteger('position_id');
            $table->unsignedInteger('menu_id');

            $table->primary(['position_id', 'menu_id'], 'mp_pk_position_menus');

            $table->foreign('position_id')
                ->references('position_id')
                ->on('mp_positions');

            $table->foreign('menu_id')
                ->references('menu_id')
                ->on('mp_menus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_position_menus');
    }
};
