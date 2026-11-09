<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrf_hr_assignments', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigInteger('request_id')->unsigned();
            $table->bigInteger('hr_admin_id')->unsigned()->nullable();
            $table->bigInteger('revision')->unsigned()->default(0);
            $table->bigInteger('assigned_by')->unsigned();
            $table->dateTime('assigned_at');
            $table->string('source', 24);

            $table->primary(['request_id']);
            $table->index(['assigned_by'], 'assigned_by');
            $table->index(['hr_admin_id'], 'idx_mrf_hr_owner');

            $table->foreign(['request_id'], 'mrf_hr_assignments_ibfk_1')->references(['id'])->on('manpower_requests')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['hr_admin_id'], 'mrf_hr_assignments_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['assigned_by'], 'mrf_hr_assignments_ibfk_3')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrf_hr_assignments');
    }
};
