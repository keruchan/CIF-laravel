<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrf_hr_assignment_history', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('request_id')->unsigned();
            $table->bigInteger('previous_hr_id')->unsigned()->nullable();
            $table->bigInteger('hr_admin_id')->unsigned()->nullable();
            $table->bigInteger('admin_id')->unsigned();
            $table->bigInteger('revision')->unsigned();
            $table->string('source', 24);
            $table->string('reason', 2000);
            $table->dateTime('created_at');

            $table->index(['admin_id'], 'admin_id');
            $table->index(['hr_admin_id'], 'hr_admin_id');
            $table->index(['request_id', 'id'], 'idx_mrf_hr_history');
            $table->index(['previous_hr_id'], 'previous_hr_id');

            $table->foreign(['request_id'], 'mrf_hr_assignment_history_ibfk_1')->references(['id'])->on('manpower_requests')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['previous_hr_id'], 'mrf_hr_assignment_history_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['hr_admin_id'], 'mrf_hr_assignment_history_ibfk_3')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['admin_id'], 'mrf_hr_assignment_history_ibfk_4')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrf_hr_assignment_history');
    }
};
