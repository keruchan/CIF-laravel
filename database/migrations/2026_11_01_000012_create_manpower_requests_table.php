<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_requests', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('mrf_number', 40);
            $table->bigInteger('department_user_id')->unsigned();
            $table->string('requestor_name', 150);
            $table->string('requestor_position', 150);
            $table->string('position_title', 150);
            $table->string('department_name', 150);
            $table->string('site_location', 250);
            $table->smallInteger('headcount')->unsigned();
            $table->longText('form_data');
            $table->enum('status', ['Pending', 'In Progress', 'Completed', 'Rejected', 'On Hold', 'Cancelled'])->default('Pending');
            $table->text('hr_notes')->nullable();
            $table->bigInteger('revision')->unsigned()->default(0);
            $table->char('submission_key', 64);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->string('batch_number', 32)->nullable();
            $table->smallInteger('headcount_index')->unsigned()->default(1);
            $table->string('legacy_mrf_number', 40)->nullable();

            $table->index(['updated_by'], 'fk_mrf_admin');
            $table->index(['department_user_id', 'created_at'], 'idx_mrf_owner_created');
            $table->index(['status', 'created_at'], 'idx_mrf_status_created');
            $table->unique(['mrf_number'], 'mrf_number');
            $table->unique(['batch_number', 'headcount_index'], 'uq_mrf_batch_headcount');
            $table->unique(['department_user_id', 'submission_key'], 'uq_mrf_submission');

            $table->foreign(['updated_by'], 'fk_mrf_admin')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['department_user_id'], 'fk_mrf_department')->references(['id'])->on('department_users')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_requests');
    }
};
