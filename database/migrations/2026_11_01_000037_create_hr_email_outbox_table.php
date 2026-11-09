<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_email_outbox', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('template_id')->unsigned()->nullable();
            $table->string('template_name', 150);
            $table->bigInteger('template_revision')->unsigned();
            $table->bigInteger('candidate_id')->unsigned()->nullable();
            $table->bigInteger('invitation_id')->unsigned()->nullable();
            $table->string('recipient_name', 400);
            $table->string('recipient_email', 254);
            $table->string('subject', 200);
            $table->text('body');
            $table->enum('status', ['Prepared', 'Sent', 'Failed', 'Cancelled'])->default('Prepared');
            $table->boolean('is_preview')->default(0);
            $table->char('preparation_key', 64);
            $table->char('preparation_hash', 64);
            $table->bigInteger('revision')->unsigned()->default(0);
            $table->bigInteger('created_by')->unsigned();
            $table->bigInteger('updated_by')->unsigned();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
            $table->dateTime('sent_at')->nullable();
            $table->string('last_error', 1000)->nullable();

            $table->index(['candidate_id'], 'candidate_id');
            $table->index(['created_by'], 'created_by');
            $table->index(['invitation_id'], 'invitation_id');
            $table->unique(['preparation_key'], 'preparation_key');
            $table->index(['status', 'created_at'], 'status');
            $table->index(['template_id'], 'template_id');
            $table->index(['updated_by'], 'updated_by');

            $table->foreign(['template_id'], 'hr_email_outbox_ibfk_1')->references(['id'])->on('hr_email_templates')->onDelete('set null')->onUpdate('restrict');
            $table->foreign(['candidate_id'], 'hr_email_outbox_ibfk_2')->references(['id'])->on('candidates')->onDelete('set null')->onUpdate('restrict');
            $table->foreign(['invitation_id'], 'hr_email_outbox_ibfk_3')->references(['id'])->on('invitations')->onDelete('set null')->onUpdate('restrict');
            $table->foreign(['created_by'], 'hr_email_outbox_ibfk_4')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['updated_by'], 'hr_email_outbox_ibfk_5')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_email_outbox');
    }
};
