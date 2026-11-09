<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_assessment_requirements', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigInteger('candidate_id')->unsigned();
            $table->boolean('department_required')->default(1);
            $table->boolean('ldt_required')->default(1);
            $table->string('reason', 1000);
            $table->integer('revision')->unsigned();
            $table->bigInteger('updated_by')->unsigned();
            $table->dateTime('updated_at');

            $table->primary(['candidate_id']);
            $table->index(['updated_by'], 'updated_by');

            $table->foreign(['candidate_id'], 'candidate_assessment_requirements_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['updated_by'], 'candidate_assessment_requirements_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_assessment_requirements');
    }
};
