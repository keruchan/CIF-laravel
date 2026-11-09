<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_interview_stage_history', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('candidate_id')->unsigned();
            $table->string('stage', 40);
            $table->string('decision', 30);
            $table->text('remarks');
            $table->dateTime('completed_at')->nullable();
            $table->integer('revision')->unsigned();
            $table->bigInteger('admin_id')->unsigned();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['admin_id'], 'admin_id');
            $table->unique(['candidate_id', 'stage', 'revision'], 'uq_candidate_stage_revision');

            $table->foreign(['candidate_id', 'stage'], 'candidate_interview_stage_history_ibfk_1')->references(['candidate_id', 'stage'])->on('candidate_interview_stages')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['admin_id'], 'candidate_interview_stage_history_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_interview_stage_history');
    }
};
