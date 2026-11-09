<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_request_approvals', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->string('batch_number', 32);
            $table->bigInteger('request_id')->unsigned();
            $table->enum('decision', ['Pending', 'Approved', 'Denied'])->default('Pending');
            $table->text('notes')->nullable();
            $table->bigInteger('revision')->unsigned()->default(0);
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->date('date_approved')->nullable();
            $table->string('position_category', 3)->nullable();
            $table->smallInteger('lead_time_days')->unsigned()->nullable();
            $table->date('hiring_due_date')->nullable();

            $table->primary(['batch_number']);
            $table->index(['decision'], 'idx_mrf_approval_decision');
            $table->unique(['request_id'], 'request_id');
            $table->index(['updated_by'], 'updated_by');

            $table->foreign(['request_id'], 'manpower_request_approvals_ibfk_1')->references(['id'])->on('manpower_requests')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['updated_by'], 'manpower_request_approvals_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_request_approvals');
    }
};
