<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_deployment_history', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('candidate_id')->unsigned();
            $table->bigInteger('request_id')->unsigned();
            $table->date('date_hired')->nullable();
            $table->string('employee_id', 50)->nullable();
            $table->string('position_title', 150)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->integer('revision')->unsigned();
            $table->bigInteger('admin_id')->unsigned();
            $table->dateTime('created_at');

            $table->index(['admin_id'], 'admin_id');
            $table->index(['request_id'], 'request_id');
            $table->unique(['candidate_id', 'revision'], 'uq_deployment_revision');

            $table->foreign(['candidate_id'], 'candidate_deployment_history_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['request_id'], 'candidate_deployment_history_ibfk_2')->references(['id'])->on('manpower_requests')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['admin_id'], 'candidate_deployment_history_ibfk_3')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_deployment_history');
    }
};
