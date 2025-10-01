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
        Schema::create('mp_employees', function (Blueprint $table) {
            $table->increments('employee_id');
            $table->string('email', 100)->unique();
            $table->string('password_hash', 255);
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->char('gender', 1);
            $table->integer('dept_id');
            $table->integer('position_id');
            $table->dateTime('created_at')->default(DB::raw('SYSDATE'));

            $table->foreign('dept_id')->references('dept_id')->on('mp_departments');
            $table->foreign('position_id')->references('position_id')->on('mp_positions');
        });
        DB::statement("ALTER TABLE mp_employees ADD CONSTRAINT mp_ck_emp_gender CHECK (gender IN ('M','F'))");
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_employees');
    }
};
