<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_assessment_requirement_history', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('candidate_id')->unsigned();
            $table->boolean('department_required');
            $table->boolean('ldt_required');
            $table->string('reason', 1000);
            $table->integer('revision')->unsigned();
            $table->bigInteger('admin_id')->unsigned();
            $table->dateTime('created_at');

            $table->index(['admin_id'], 'admin_id');
            $table->unique(['candidate_id', 'revision'], 'uq_assessment_requirement_revision');

            $table->foreign(['candidate_id'], 'candidate_assessment_requirement_history_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['admin_id'], 'candidate_assessment_requirement_history_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_assessment_requirement_history');
    }
};
