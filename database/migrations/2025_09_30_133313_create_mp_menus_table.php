<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mp_menus', function (Blueprint $table) {
            $table->increments('menu_id');
            $table->string('key_name', 50)->unique();
            $table->string('name', 100);
            $table->dateTime('created_at')->default(DB::raw('SYSDATE'));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_menus');
    }
};
