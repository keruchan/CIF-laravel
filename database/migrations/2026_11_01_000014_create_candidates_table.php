<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('candidate_number', 32);
            $table->bigInteger('invitation_id')->unsigned();
            $table->string('email', 254);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('position', 150)->nullable();
            $table->string('status', 40)->default('Screening');
            $table->string('legacy_status', 30)->nullable();
            $table->string('workflow_legacy_status', 40)->nullable();
            $table->longText('form_data');
            $table->dateTime('submitted_at')->useCurrent();
            $table->dateTime('archived_at')->nullable();
            $table->bigInteger('archived_by')->unsigned()->nullable();
            $table->string('legacy_candidate_number', 32)->nullable();

            $table->unique(['candidate_number'], 'candidate_number');
            $table->index(['archived_by'], 'fk_candidate_archiver');
            $table->index(['archived_at'], 'idx_candidate_archive');
            $table->index(['last_name', 'first_name'], 'idx_candidate_name');
            $table->index(['status'], 'idx_candidate_status');
            $table->index(['submitted_at'], 'idx_candidate_submitted');
            $table->unique(['invitation_id'], 'invitation_id');

            $table->foreign(['archived_by'], 'fk_candidate_archiver')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['invitation_id'], 'fk_candidate_invitation')->references(['id'])->on('invitations')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
