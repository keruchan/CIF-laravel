<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_interview_stages', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigInteger('candidate_id')->unsigned();
            $table->string('stage', 40);
            $table->string('decision', 30)->default('Pending Decision');
            $table->text('remarks');
            $table->dateTime('completed_at')->nullable();
            $table->integer('revision')->unsigned()->default(1);
            $table->bigInteger('updated_by')->unsigned();
            $table->dateTime('updated_at')->useCurrent();

            $table->primary(['candidate_id', 'stage']);
            $table->index(['updated_by'], 'updated_by');

            $table->foreign(['candidate_id'], 'candidate_interview_stages_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['updated_by'], 'candidate_interview_stages_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_interview_stages');
    }
};
