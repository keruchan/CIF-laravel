<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_deployments', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigInteger('candidate_id')->unsigned();
            $table->bigInteger('request_id')->unsigned();
            $table->date('date_hired')->nullable();
            $table->string('employee_id', 50)->nullable();
            $table->string('position_title', 150)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->integer('revision')->unsigned();
            $table->bigInteger('updated_by')->unsigned();
            $table->dateTime('updated_at');

            $table->primary(['candidate_id']);
            $table->index(['request_id'], 'request_id');
            $table->index(['updated_by'], 'updated_by');
            $table->unique(['employee_id'], 'uq_deployment_employee_id');

            $table->foreign(['candidate_id'], 'candidate_deployments_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['request_id'], 'candidate_deployments_ibfk_2')->references(['id'])->on('manpower_requests')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['updated_by'], 'candidate_deployments_ibfk_3')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_deployments');
    }
};
