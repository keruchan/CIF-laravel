<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_hr_phases', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigInteger('candidate_id')->unsigned();
            $table->string('stage', 30);
            $table->bigInteger('started_by')->unsigned();
            $table->dateTime('started_at');
            $table->bigInteger('updated_by')->unsigned();
            $table->dateTime('updated_at');
            $table->bigInteger('completed_by')->unsigned()->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('decision', 30)->nullable();

            $table->primary(['candidate_id', 'stage']);
            $table->index(['completed_by', 'completed_at'], 'hr_phase_finished');
            $table->index(['started_by', 'completed_at'], 'hr_phase_started');
            $table->index(['updated_at'], 'hr_phase_updated');
            $table->index(['updated_by'], 'updated_by');

            $table->foreign(['candidate_id'], 'candidate_hr_phases_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['started_by'], 'candidate_hr_phases_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['updated_by'], 'candidate_hr_phases_ibfk_3')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['completed_by'], 'candidate_hr_phases_ibfk_4')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_hr_phases');
    }
};
