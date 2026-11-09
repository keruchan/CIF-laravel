<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_mrf_assignments', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigInteger('revision_id')->unsigned();
            $table->bigInteger('candidate_id')->unsigned();
            $table->bigInteger('request_id')->unsigned()->nullable();

            $table->primary(['revision_id']);
            $table->index(['candidate_id', 'revision_id'], 'idx_candidate_mrf');
            $table->index(['request_id', 'candidate_id'], 'idx_mrf_candidate');

            $table->foreign(['candidate_id'], 'fk_candidate_mrf_candidate')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['request_id'], 'fk_candidate_mrf_request')->references(['id'])->on('manpower_requests')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['revision_id'], 'fk_candidate_mrf_revision')->references(['id'])->on('candidate_admin_revisions')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_mrf_assignments');
    }
};
