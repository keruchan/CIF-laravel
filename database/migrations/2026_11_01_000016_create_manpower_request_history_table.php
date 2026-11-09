<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_request_history', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->bigInteger('request_id')->unsigned();
            $table->bigInteger('admin_id')->unsigned();
            $table->string('previous_status', 20);
            $table->string('status', 20);
            $table->text('hr_notes')->nullable();
            $table->dateTime('created_at');

            $table->index(['admin_id'], 'fk_mrf_history_admin');
            $table->index(['request_id'], 'fk_mrf_history_request');

            $table->foreign(['admin_id'], 'fk_mrf_history_admin')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['request_id'], 'fk_mrf_history_request')->references(['id'])->on('manpower_requests')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_request_history');
    }
};
