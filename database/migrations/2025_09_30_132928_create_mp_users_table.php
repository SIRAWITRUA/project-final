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
        Schema::create('mp_users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email', 100)->unique();
            $table->string('password_hash', 255);
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->char('gender', 1);
            $table->dateTime('created_at')->default(DB::raw('SYSDATE'));
        });

        DB::statement("ALTER TABLE mp_users ADD CONSTRAINT mp_ck_users_gender CHECK (gender IN ('M','F', 'N'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_users');
    }
};
