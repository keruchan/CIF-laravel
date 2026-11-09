<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_request_approval_history', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('batch_number', 32);
            $table->bigInteger('admin_id')->unsigned();
            $table->string('previous_decision', 12);
            $table->string('decision', 12);
            $table->text('notes')->nullable();
            $table->bigInteger('revision')->unsigned();
            $table->dateTime('created_at');
            $table->date('date_approved')->nullable();
            $table->string('position_category', 3)->nullable();
            $table->smallInteger('lead_time_days')->unsigned()->nullable();
            $table->date('hiring_due_date')->nullable();

            $table->index(['admin_id'], 'admin_id');
            $table->unique(['batch_number', 'revision'], 'uq_mrf_approval_revision');

            $table->foreign(['batch_number'], 'manpower_request_approval_history_ibfk_1')->references(['batch_number'])->on('manpower_request_approvals')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['admin_id'], 'manpower_request_approval_history_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_request_approval_history');
    }
};
